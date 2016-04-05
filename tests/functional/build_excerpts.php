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

/* @link https://github.com/sphinxsearch/sphinx/blob/master/api/test2.php */

$file = __DIR__.'/../../vendor/autoload.php';

if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run test suite. "php composer.phar install --dev"');
}

require_once __DIR__.'/../../vendor/autoload.php';

$docs = array
(
    'this is my test text to be highlighted, and for the sake of the testing we need to pump its length somewhat',
    'another test text to be highlighted, below limit',
    'test number three, without phrase match',
    'final test, not only without phrase match, but also above limit and with swapped phrase text test as well',
);
$words = 'test text';
$index = 'test1';
$opts = array
(
    'before_match'    => '<b>',
    'after_match'     => '</b>',
    'chunk_separator' => ' ... ',
    'limit'           => 60,
    'around'          => 3,
);

foreach (array(0, 1) as $exact) {
    $opts['exact_phrase'] = $exact;
    print 'exact_phrase=' . $exact . PHP_EOL;
    $cl = new SphinxClient();
    $res = $cl->buildExcerpts($docs, $index, $words, $opts);
    if (!$res) {
        exit(sprintf('ERROR: %s.' . PHP_EOL, $cl->getLastError()));
    } else {
        $n = 0;
        foreach ($res as $entry) {
            $n++;
            printf('n=%s, res=%s' . PHP_EOL, $n, $entry);
        }
        print PHP_EOL;
    }
}
