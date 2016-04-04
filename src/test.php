<?php

//
// $Id$
//

$file = __DIR__.'/../vendor/autoload.php';

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
for($i = 0; $i < count($args); $i++) {
    $arg = $args[$i];

    if ($arg == '-h' || $arg=='--host') {
        $host = $args[++$i];
    } elseif ($arg == '-p' || $arg == '--port') {
        $port = (int)$args[++$i];
    } elseif ($arg == '-i' || $arg == '--index') {
        $index = $args[++$i];
    } elseif ($arg == '-s' || $arg == '--sortby') {
        $sortby = $args[++$i];
        $sortexpr = '';
    } elseif ($arg == '-S' || $arg == '--sortexpr') {
        $sortexpr = $args[++$i];
        $sortby = '';
    } elseif ($arg == '-a' || $arg == '--any') {
        $mode = SPH_MATCH_ANY;
    } elseif ($arg == '-b' || $arg == '--boolean') {
        $mode = SPH_MATCH_BOOLEAN;
    } elseif ($arg == '-e' || $arg == '--extended') {
        $mode = SPH_MATCH_EXTENDED;
    } elseif ($arg == '-e2') {
        $mode = SPH_MATCH_EXTENDED2;
    } elseif ($arg == '-ph'|| $arg == '--phrase') {
        $mode = SPH_MATCH_PHRASE;
    } elseif ($arg == '-f' || $arg == '--filter') {
        $filter = $args[++$i];
    } elseif ($arg == '-v' || $arg == '--value') {
        $filtervals[] = $args[++$i];
    } elseif ($arg == '-g' || $arg == '--groupby') {
        $groupby = $args[++$i];
    } elseif ($arg == '-gs'|| $arg == '--groupsort') {
        $groupsort = $args[++$i];
    } elseif ($arg == '-d' || $arg == '--distinct') {
        $distinct = $args[++$i];
    } elseif ($arg == '-l' || $arg == '--limit') {
        $limit = (int)$args[++$i];
    } elseif ($arg == '--select') {
        $select = $args[++$i];
    } elseif ($arg == '-fr' || $arg == '--filterrange') {
        $cl->setFilterRange($args[++$i], $args[++$i], $args[++$i]);
    } elseif ($arg == '-r') {
        $arg = strtolower($args[++$i]);
        if ($arg == 'bm25') {
            $ranker = SPH_RANK_BM25;
        }
        if ($arg == 'none') {
            $ranker = SPH_RANK_NONE;
        }
        if ($arg == 'wordcount') {
            $ranker = SPH_RANK_WORDCOUNT;
        }
        if ($arg == 'fieldmask') {
            $ranker = SPH_RANK_FIELDMASK;
        }
        if ($arg == 'sph04') {
            $ranker = SPH_RANK_SPH04;
        }
    } else {
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
