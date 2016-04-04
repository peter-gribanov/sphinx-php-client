<?php

//
// $Id$
//

$file = __DIR__.'/../vendor/autoload.php';

if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run test suite. "php composer.phar install --dev"');
}

require_once __DIR__.'/../vendor/autoload.php';

//////////////////////
// parse command line
//////////////////////

// for very old PHP versions, like at my home test server
if (is_array($argv) && !isset($_SERVER['argv'])) {
    $_SERVER['argv'] = $argv;
}
unset($_SERVER['argv'][0]);

// build query
if (!is_array($_SERVER['argv']) || empty($_SERVER['argv'])) {
    print <<<EOF
Usage: php -f test.php [OPTIONS] query words

Options are:
-h, --host <HOST>      connect to searchd at host HOST
-p, --port             connect to searchd at port PORT
-i, --index <IDX>      search through index(es) specified by IDX
-s, --sortby <CLAUSE>  sort matches by 'CLAUSE' in sort_extended mode
-S, --sortexpr <EXPR>  sort matches by 'EXPR' DESC in sort_expr mode
-a, --any              use 'match any word' matching mode
-b, --boolean          use 'boolean query' matching mode
-e, --extended         use 'extended query' matching mode
-ph,--phrase           use 'exact phrase' matching mode
-f, --filter <ATTR>    filter by attribute 'ATTR' (default is 'group_id')
-fr,--filterrange <ATTR> <MIN> <MAX>
                       add specified range filter
-v, --value <VAL>      add VAL to allowed 'group_id' values list
-g, --groupby <EXPR>   group matches by 'EXPR'
-gs,--groupsort <EXPR> sort groups by 'EXPR'
-d, --distinct <ATTR>  count distinct values of 'ATTR'
-l, --limit <COUNT>    retrieve COUNT matches (default: 20)
--select <EXPRLIST>    use 'EXPRLIST' as select-list (default: *)
EOF;
    exit;
}

$args = array();
foreach ($_SERVER['argv'] as $arg) {
    $args[] = $arg;
}

$cl = new SphinxClient();

$q = '';
$sql = '';
$mode = SPH_MATCH_ALL;
$host = 'localhost';
$port = 9312;
$index = '*';
$groupby = '';
$groupsort = '@group desc';
$filter = 'group_id';
$filtervals = array();
$distinct = '';
$sortby = '';
$sortexpr = '';
$limit = 20;
$ranker = SPH_RANK_PROXIMITY_BM25;
$select = '';

for ($i = 0; $i < count($args); $i++) {
    switch ($args[$i]) {
        case '-h':
        case '--host':
            $host = $args[++$i];
            break;
        case '-p':
        case '--port':
            $port = (int)$args[++$i];
            break;
        case '-i':
        case '--index':
            $index = $args[++$i];
            break;
        case '-s':
        case '--sortby':
            $sortby = $args[++$i];
            $sortexpr = '';
            break;
        case '-S':
        case '--sortexpr':
            $sortexpr = $args[++$i];
            $sortby = '';
            break;
        case '-a':
        case '--any':
            $mode = SPH_MATCH_ANY;
            break;
        case '-b':
        case '--boolean':
            $mode = SPH_MATCH_BOOLEAN;
            break;
        case '-e':
        case '--extended':
            $mode = SPH_MATCH_EXTENDED;
            break;
        case '-e2':
            $mode = SPH_MATCH_EXTENDED2;
            break;
        case '-ph':
        case '--phrase':
            $mode = SPH_MATCH_PHRASE;
            break;
        case '-f':
        case '--filter':
            $filter = $args[++$i];
            break;
        case '-v':
        case '--value':
            $filtervals[] = $args[++$i];
            break;
        case '-g':
        case '--groupby':
            $groupby = $args[++$i];
            break;
        case '-gs':
        case '--groupsort':
            $groupsort = $args[++$i];
            break;
        case '-d':
        case '--distinct':
            $distinct = $args[++$i];
            break;
        case '-l':
        case '--limit':
            $limit = (int)$args[++$i];
            break;
        case '--select':
            $select = $args[++$i];
            break;
        case '-fr':
        case '--filterrange':
            $cl->setFilterRange($args[++$i], $args[++$i], $args[++$i]);
            break;
        case '-r':
            switch (strtolower($args[++$i])) {
                case 'bm25':
                    $ranker = SPH_RANK_BM25;
                    break;
                case 'none':
                    $ranker = SPH_RANK_NONE;
                    break;
                case 'wordcount':
                    $ranker = SPH_RANK_WORDCOUNT;
                    break;
                case 'fieldmask':
                    $ranker = SPH_RANK_FIELDMASK;
                    break;
                case 'sph04':
                    $ranker = SPH_RANK_SPH04;
                    break;
            }
            break;
        default:
            $q .= $args[$i] . ' ';
    }
}

////////////
// do query
////////////

$cl->setServer($host, $port);
$cl->setConnectTimeout(1);
$cl->setArrayResult(true);
$cl->setMatchMode($mode);
if (count($filtervals)) {
    $cl->setFilter($filter, $filtervals);
}
if ($groupby) {
    $cl->setGroupBy($groupby, SPH_GROUPBY_ATTR, $groupsort);
}
if ($sortby) {
    $cl->setSortMode(SPH_SORT_EXTENDED, $sortby);
}
if ($sortexpr) {
    $cl->setSortMode(SPH_SORT_EXPR, $sortexpr);
}
if ($distinct) {
    $cl->setGroupDistinct($distinct);
}
if ($select) {
    $cl->setSelect($select);
}
if ($limit) {
    $cl->setLimits(0, $limit,($limit>1000) ? $limit : 1000);
}
$cl->setRankingMode($ranker);
$res = $cl->query($q, $index);

////////////////
// print me out
////////////////

if ($res === false) {
    printf('Query failed: %s.' . PHP_EOL, $cl->getLastError());

} else {
    if ($cl->getLastWarning()) {
        printf('WARNING: %s' . PHP_EOL . PHP_EOL, $cl->getLastWarning());
    }

    print "Query '$q' retrieved {$res['total']} of {$res['total_found']} matches in {$res['time']} sec.\n";
    print 'Query stats:' . PHP_EOL;
    if (is_array($res['words'])) {
        foreach ($res['words'] as $word => $info) {
            print "    '$word' found {$info['hits']} times in {$info['docs']} documents\n";
        }
    }
    print PHP_EOL;

    if (is_array($res['matches'])) {
        $n = 1;
        print 'Matches:' . PHP_EOL;
        foreach ($res['matches'] as $docinfo) {
            print "$n. doc_id={$docinfo['id']}, weight={$docinfo['weight']}";
            foreach ($res['attrs'] as $attrname => $attrtype) {
                $value = $docinfo['attrs'][$attrname];
                if ($attrtype == SPH_ATTR_MULTI || $attrtype == SPH_ATTR_MULTI64) {
                    $value = '(' . join(',', $value) .')';
                } elseif ($attrtype == SPH_ATTR_TIMESTAMP) {
                    $value = date('Y-m-d H:i:s', $value);
                }
                print ", $attrname=$value";
            }
            print PHP_EOL;
            $n++;
        }
    }
}
