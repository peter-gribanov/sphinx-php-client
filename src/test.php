<?php
/**
 * $Id$
 */

/**
 * Copyright (c) 2001-2015, Andrew Aksyonoff
 * Copyright (c) 2008-2015, Sphinx Technologies Inc
 * All rights reserved
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Library General Public License. You should
 * have received a copy of the LGPL license along with this program; if you
 * did not, you can find it at http://www.gnu.org/
 */

namespace Sphinx;

$file = __DIR__.'/../vendor/autoload.php';

if (!file_exists($file)) {
    throw new \RuntimeException('Install dependencies to run test suite. "php composer.phar install --dev"');
}

require_once __DIR__.'/../vendor/autoload.php';

//////////////////////
// parse command line
//////////////////////

// for very old PHP versions, like at my home test server
if (isset($argv) && is_array($argv) && !isset($_SERVER['argv'])) {
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
} else {

    $args = array();
    foreach ($_SERVER['argv'] as $arg) {
        $args[] = $arg;
    }

    $cl = new Client();

    $q = '';
    $mode = Client::MATCH_ALL;
    $host = 'localhost';
    $port = 9312;
    $index = '*';
    $group_by = '';
    $group_sort = '@group desc';
    $filter = 'group_id';
    $filter_values = array();
    $distinct = '';
    $sort_by = '';
    $sort_expr = '';
    $limit = 20;
    $ranker = Client::RANK_PROXIMITY_BM25;
    $select = '';
    $count = count($args);

    for ($i = 0; $i < $count; $i++) {
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
                $sort_by = $args[++$i];
                $sort_expr = '';
                break;
            case '-S':
            case '--sortexpr':
                $sort_expr = $args[++$i];
                $sort_by = '';
                break;
            case '-a':
            case '--any':
                $mode = Client::MATCH_ANY;
                break;
            case '-b':
            case '--boolean':
                $mode = Client::MATCH_BOOLEAN;
                break;
            case '-e':
            case '--extended':
                $mode = Client::MATCH_EXTENDED;
                break;
            case '-e2':
                $mode = Client::MATCH_EXTENDED2;
                break;
            case '-ph':
            case '--phrase':
                $mode = Client::MATCH_PHRASE;
                break;
            case '-f':
            case '--filter':
                $filter = $args[++$i];
                break;
            case '-v':
            case '--value':
                $filter_values[] = $args[++$i];
                break;
            case '-g':
            case '--groupby':
                $group_by = $args[++$i];
                break;
            case '-gs':
            case '--groupsort':
                $group_sort = $args[++$i];
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
                        $ranker = Client::RANK_BM25;
                        break;
                    case 'none':
                        $ranker = Client::RANK_NONE;
                        break;
                    case 'wordcount':
                        $ranker = Client::RANK_WORD_COUNT;
                        break;
                    case 'fieldmask':
                        $ranker = Client::RANK_FIELD_MASK;
                        break;
                    case 'sph04':
                        $ranker = Client::RANK_SPH04;
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
    if (count($filter_values)) {
        $cl->setFilter($filter, $filter_values);
    }
    if ($group_by) {
        $cl->setGroupBy($group_by, Client::GROUP_BY_ATTR, $group_sort);
    }
    if ($sort_by) {
        $cl->setSortMode(Client::SORT_EXTENDED, $sort_by);
    }
    if ($sort_expr) {
        $cl->setSortMode(Client::SORT_EXPR, $sort_expr);
    }
    if ($distinct) {
        $cl->setGroupDistinct($distinct);
    }
    if ($select) {
        $cl->setSelect($select);
    }
    if ($limit) {
        $cl->setLimits(0, $limit, ($limit > 1000) ? $limit : 1000);
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
            foreach ($res['matches'] as $doc_info) {
                print "$n. doc_id={$doc_info['id']}, weight={$doc_info['weight']}";
                foreach ($res['attrs'] as $attr_name => $attr_type) {
                    $value = $doc_info['attrs'][$attr_name];
                    if ($attr_type == Client::ATTR_MULTI || $attr_type == Client::ATTR_MULTI64) {
                        $value = '(' . join(',', $value) . ')';
                    } elseif ($attr_type == Client::ATTR_TIMESTAMP) {
                        $value = date('Y-m-d H:i:s', $value);
                    }
                    print ", $attr_name=$value";
                }
                print PHP_EOL;
                $n++;
            }
        }
    }
}
