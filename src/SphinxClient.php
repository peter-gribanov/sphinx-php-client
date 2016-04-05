<?php

//
// $Id$
//

//
// Copyright (c) 2001-2015, Andrew Aksyonoff
// Copyright (c) 2008-2015, Sphinx Technologies Inc
// All rights reserved
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU Library General Public License. You should
// have received a copy of the LGPL license along with this program; if you
// did not, you can find it at http://www.gnu.org/
//
// WARNING!!!
//
// As of 2015, we strongly recommend to use either SphinxQL or REST APIs
// rather than the native SphinxAPI.
//
// While both the native SphinxAPI protocol and the existing APIs will
// continue to exist, and perhaps should not even break (too much), exposing
// all the new features via multiple different native API implementations
// is too much of a support complication for us.
//
// That said, you're welcome to overtake the maintenance of any given
// official API, and remove this warning ;)
//

/////////////////////////////////////////////////////////////////////////////
// PHP version of Sphinx searchd client (PHP API)
/////////////////////////////////////////////////////////////////////////////

// known searchd commands
define('SEARCHD_COMMAND_SEARCH',     0);
define('SEARCHD_COMMAND_EXCERPT',    1);
define('SEARCHD_COMMAND_UPDATE',     2);
define('SEARCHD_COMMAND_KEYWORDS',   3);
define('SEARCHD_COMMAND_PERSIST',    4);
define('SEARCHD_COMMAND_STATUS',     5);
define('SEARCHD_COMMAND_FLUSHATTRS', 7);

// current client-side command implementation versions
define('VER_COMMAND_SEARCH',     0x11E);
define('VER_COMMAND_EXCERPT',    0x104);
define('VER_COMMAND_UPDATE',     0x103);
define('VER_COMMAND_KEYWORDS',   0x100);
define('VER_COMMAND_STATUS',     0x101);
define('VER_COMMAND_QUERY',      0x100);
define('VER_COMMAND_FLUSHATTRS', 0x100);

// known searchd status codes
define('SEARCHD_OK',      0);
define('SEARCHD_ERROR',   1);
define('SEARCHD_RETRY',   2);
define('SEARCHD_WARNING', 3);

// known match modes
define('SPH_MATCH_ALL',       0);
define('SPH_MATCH_ANY',       1);
define('SPH_MATCH_PHRASE',    2);
define('SPH_MATCH_BOOLEAN',   3);
define('SPH_MATCH_EXTENDED',  4);
define('SPH_MATCH_FULLSCAN',  5);
define('SPH_MATCH_EXTENDED2', 6); // extended engine V2 (TEMPORARY, WILL BE REMOVED)

// known ranking modes (ext2 only)
define('SPH_RANK_PROXIMITY_BM25', 0); // default mode, phrase proximity major factor and BM25 minor one
define('SPH_RANK_BM25',           1); // statistical mode, BM25 ranking only (faster but worse quality)
define('SPH_RANK_NONE',           2); // no ranking, all matches get a weight of 1
define('SPH_RANK_WORDCOUNT',      3); // simple word-count weighting, rank is a weighted sum of per-field keyword occurence counts
define('SPH_RANK_PROXIMITY',      4);
define('SPH_RANK_MATCHANY',       5);
define('SPH_RANK_FIELDMASK',      6);
define('SPH_RANK_SPH04',          7);
define('SPH_RANK_EXPR',           8);
define('SPH_RANK_TOTAL',          9);

// known sort modes
define('SPH_SORT_RELEVANCE',     0);
define('SPH_SORT_ATTR_DESC',     1);
define('SPH_SORT_ATTR_ASC',      2);
define('SPH_SORT_TIME_SEGMENTS', 3);
define('SPH_SORT_EXTENDED',      4);
define('SPH_SORT_EXPR',          5);

// known filter types
define('SPH_FILTER_VALUES',     0);
define('SPH_FILTER_RANGE',      1);
define('SPH_FILTER_FLOATRANGE', 2);
define('SPH_FILTER_STRING',     3);

// known attribute types
define('SPH_ATTR_INTEGER',   1);
define('SPH_ATTR_TIMESTAMP', 2);
define('SPH_ATTR_ORDINAL',   3);
define('SPH_ATTR_BOOL',      4);
define('SPH_ATTR_FLOAT',     5);
define('SPH_ATTR_BIGINT',    6);
define('SPH_ATTR_STRING',    7);
define('SPH_ATTR_FACTORS',   1001);
define('SPH_ATTR_MULTI',     0x40000001);
define('SPH_ATTR_MULTI64',   0x40000002);

// known grouping functions
define('SPH_GROUPBY_DAY',      0);
define('SPH_GROUPBY_WEEK',     1);
define('SPH_GROUPBY_MONTH',    2);
define('SPH_GROUPBY_YEAR',     3);
define('SPH_GROUPBY_ATTR',     4);
define('SPH_GROUPBY_ATTRPAIR', 5);

// important properties of PHP's integers:
//  - always signed (one bit short of PHP_INT_SIZE)
//  - conversion from string to int is saturated
//  - float is double
//  - div converts arguments to floats
//  - mod converts arguments to ints

// the packing code below works as follows:
//  - when we got an int, just pack it
//    if performance is a problem, this is the branch users should aim for
//
//  - otherwise, we got a number in string form
//    this might be due to different reasons, but we assume that this is
//    because it didn't fit into PHP int
//
//  - factor the string into high and low ints for packing
//    - if we have bcmath, then it is used
//    - if we don't, we have to do it manually (this is the fun part)
//
//    - x64 branch does factoring using ints
//    - x32 (ab)uses floats, since we can't fit unsigned 32-bit number into an int
//
// unpacking routines are pretty much the same.
//  - return ints if we can
//  - otherwise format number into a string

/**
 * Pack 64-bit signed
 *
 * @param int $value
 *
 * @return string
 */
function sphPackI64($value)
{
    assert(is_numeric($value));

    // x64
    if (PHP_INT_SIZE >= 8) {
        $value = (int)$value;
        return pack('NN', $value >> 32, $value & 0xFFFFFFFF);
    }

    // x32, int
    if (is_int($value)) {
        return pack('NN', $value < 0 ? -1 : 0, $value);
    }

    // x32, bcmath
    if (function_exists('bcmul')) {
        if (bccomp($value, 0) == -1) {
            $value = bcadd('18446744073709551616', $value);
        }
        $h = bcdiv($value, '4294967296', 0);
        $l = bcmod($value, '4294967296');
        return pack('NN', (float)$h, (float)$l); // conversion to float is intentional; int would lose 31st bit
    }

    // x32, no-bcmath
    $p = max(0, strlen($value) - 13);
    $lo = abs((float)substr($value, $p));
    $hi = abs((float)substr($value, 0, $p));

    $m = $lo + $hi * 1316134912.0; // (10 ^ 13) % (1 << 32) = 1316134912
    $q = floor($m / 4294967296.0);
    $l = $m - ($q * 4294967296.0);
    $h = $hi * 2328.0 + $q; // (10 ^ 13) / (1 << 32) = 2328

    if ($value < 0) {
        if ($l == 0) {
            $h = 4294967296.0 - $h;
        } else {
            $h = 4294967295.0 - $h;
            $l = 4294967296.0 - $l;
        }
    }
    return pack('NN', $h, $l);
}

/**
 * Ppack 64-bit unsigned
 *
 * @param int $value
 *
 * @return string
 */
function sphPackU64($value)
{
    assert(is_numeric($value));

    // x64
    if (PHP_INT_SIZE >= 8) {
        assert($value >= 0);

        // x64, int
        if (is_int($value)) {
            return pack('NN', $value >> 32, $value & 0xFFFFFFFF);
        }

        // x64, bcmath
        if (function_exists('bcmul')) {
            $h = bcdiv($value, 4294967296, 0);
            $l = bcmod($value, 4294967296);
            return pack('NN', $h, $l);
        }

        // x64, no-bcmath
        $p = max(0, strlen($value) - 13);
        $lo = (int)substr($value, $p);
        $hi = (int)substr($value, 0, $p);

        $m = $lo + $hi * 1316134912;
        $l = $m % 4294967296;
        $h = $hi * 2328 + (int)($m / 4294967296);

        return pack('NN', $h, $l);
    }

    // x32, int
    if (is_int($value)) {
        return pack('NN', 0, $value);
    }

    // x32, bcmath
    if (function_exists('bcmul')) {
        $h = bcdiv($value, '4294967296', 0);
        $l = bcmod($value, '4294967296');
        return pack('NN', (float)$h, (float)$l); // conversion to float is intentional; int would lose 31st bit
    }

    // x32, no-bcmath
    $p = max(0, strlen($value) - 13);
    $lo = (float)substr($value, $p);
    $hi = (float)substr($value, 0, $p);

    $m = $lo + $hi * 1316134912.0;
    $q = floor($m / 4294967296.0);
    $l = $m - ($q * 4294967296.0);
    $h = $hi * 2328.0 + $q;

    return pack('NN', $h, $l);
}

/**
 * Unpack 64-bit unsigned
 *
 * @param string $value
 *
 * @return string
 */
function sphUnpackU64($value)
{
    list($hi, $lo) = array_values(unpack('N*N*', $value));

    if (PHP_INT_SIZE >= 8) {
        if ($hi < 0) { // because php 5.2.2 to 5.2.5 is totally fucked up again
            $hi += 1 << 32;
        }
        if ($lo < 0) {
            $lo += 1 << 32;
        }

        // x64, int
        if ($hi <= 2147483647) {
            return ($hi << 32) + $lo;
        }

        // x64, bcmath
        if (function_exists('bcmul')) {
            return bcadd($lo, bcmul($hi, '4294967296'));
        }

        // x64, no-bcmath
        $C = 100000;
        $h = ((int)($hi / $C) << 32) + (int)($lo / $C);
        $l = (($hi % $C) << 32) + ($lo % $C);
        if ($l > $C) {
            $h += (int)($l / $C);
            $l  = $l % $C;
        }

        if ($h == 0) {
            return $l;
        }
        return sprintf('%d%05d', $h, $l);
    }

    // x32, int
    if ($hi == 0) {
        if ($lo > 0) {
            return $lo;
        }
        return sprintf('%u', $lo);
    }

    $hi = sprintf('%u', $hi);
    $lo = sprintf('%u', $lo);

    // x32, bcmath
    if (function_exists('bcmul')) {
        return bcadd($lo, bcmul($hi, '4294967296'));
    }

    // x32, no-bcmath
    $hi = (float)$hi;
    $lo = (float)$lo;

    $q = floor($hi / 10000000.0);
    $r = $hi - $q * 10000000.0;
    $m = $lo + $r * 4967296.0;
    $mq = floor($m / 10000000.0);
    $l = $m - $mq * 10000000.0;
    $h = $q * 4294967296.0 + $r * 429.0 + $mq;

    $h = sprintf('%.0f', $h);
    $l = sprintf('%07.0f', $l);
    if ($h == '0') {
        return sprintf('%.0f', (float)$l);
    }
    return $h . $l;
}

/**
 * Unpack 64-bit signed
 *
 * @param string $value
 *
 * @return string
 */
function sphUnpackI64($value)
{
    list($hi, $lo) = array_values(unpack('N*N*', $value));

    // x64
    if (PHP_INT_SIZE >= 8) {
        if ($hi < 0) { // because php 5.2.2 to 5.2.5 is totally fucked up again
            $hi += 1 << 32;
        }
        if ($lo < 0) {
            $lo += 1 << 32;
        }

        return ($hi << 32) + $lo;
    }

    if ($hi == 0) { // x32, int
        if ($lo > 0) {
            return $lo;
        }
        return sprintf('%u', $lo);
    } elseif ($hi == -1) { // x32, int
        if ($lo < 0) {
            return $lo;
        }
        return sprintf('%.0f', $lo - 4294967296.0);
    }

    $neg = '';
    $c = 0;
    if ($hi < 0) {
        $hi = ~$hi;
        $lo = ~$lo;
        $c = 1;
        $neg = '-';
    }

    $hi = sprintf('%u', $hi);
    $lo = sprintf('%u', $lo);

    // x32, bcmath
    if (function_exists('bcmul')) {
        return $neg . bcadd(bcadd($lo, bcmul($hi, '4294967296')), $c);
    }

    // x32, no-bcmath
    $hi = (float)$hi;
    $lo = (float)$lo;

    $q = floor($hi / 10000000.0);
    $r = $hi - $q * 10000000.0;
    $m = $lo + $r * 4967296.0;
    $mq = floor($m / 10000000.0);
    $l = $m - $mq * 10000000.0 + $c;
    $h = $q * 4294967296.0 + $r * 429.0 + $mq;
    if ($l == 10000000) {
        $l = 0;
        $h += 1;
    }

    $h = sprintf('%.0f', $h);
    $l = sprintf('%07.0f', $l);
    if ($h == '0') {
        return $neg . sprintf('%.0f', (float)$l);
    }
    return $neg . $h . $l;
}

/**
 * @param int $value
 *
 * @return int|string
 */
function sphFixUint($value)
{
    if (PHP_INT_SIZE >= 8) {
        // x64 route, workaround broken unpack() in 5.2.2+
        if ($value < 0) {
            $value += 1 << 32;
        }
        return $value;
    } else {
        // x32 route, workaround php signed/unsigned braindamage
        return sprintf('%u', $value);
    }
}

/**
 * @param int $flag
 * @param int $bit
 * @param bool $on
 *
 * @return int
 */
function sphSetBit($flag, $bit, $on)
{
    if ($on) {
        $flag |= 1 << $bit;
    } else {
        $reset = 16777215 ^ (1 << $bit);
        $flag = $flag & $reset;
    }
    return $flag;
}


/**
 * Sphinx searchd client class
 */
class SphinxClient
{
    /**
     * Searchd host
     *
     * @var string
     */
    protected $host = 'localhost';

    /**
     * Searchd port
     *
     * @var int
     */
    protected $port = 9312;

    /**
     * How many records to seek from result-set start
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * How many records to return from result-set starting at offset
     *
     * @var int
     */
    protected $limit = 20;

    /**
     * Query matching mode
     *
     * @var int
     */
    protected $mode = SPH_MATCH_EXTENDED2;

    /**
     * Per-field weights (default is 1 for all fields)
     *
     * @var array
     */
    protected $weights = array();

    /**
     * Match sorting mode
     *
     * @var int
     */
    protected $sort = SPH_SORT_RELEVANCE;

    /**
     * Attribute to sort by
     *
     * @var string
     */
    protected $sort_by = '';

    /**
     * Min ID to match (0 means no limit)
     *
     * @var int
     */
    protected $min_id = 0;

    /**
     * Max ID to match (0 means no limit)
     *
     * @var int
     */
    protected $max_id = 0;

    /**
     * Search filters
     *
     * @var array
     */
    protected $filters = array();

    /**
     * Group-by attribute name
     *
     * @var string
     */
    protected $group_by = '';

    /**
     * Group-by function (to pre-process group-by attribute value with)
     *
     * @var int
     */
    protected $group_func = SPH_GROUPBY_DAY;

    /**
     * Group-by sorting clause (to sort groups in result set with)
     *
     * @var string
     */
    protected $group_sort = '@group desc';

    /**
     * Group-by count-distinct attribute
     *
     * @var string
     */
    protected $group_distinct = '';

    /**
     * Max matches to retrieve
     *
     * @var int
     */
    protected $max_matches = 1000;

    /**
     * Cutoff to stop searching at
     *
     * @var int
     */
    protected $cutoff = 0;

    /**
     * Distributed retries count
     *
     * @var int
     */
    protected $retry_count = 0;

    /**
     * Distributed retries delay
     *
     * @var int
     */
    protected $retry_delay = 0;

    /**
     * Geographical anchor point
     *
     * @var array
     */
    protected $anchor = array();

    /**
     * Per-index weights
     *
     * @var array
     */
    protected $index_weights = array();

    /**
     * Ranking mode
     *
     * @var int
     */
    protected $ranker = SPH_RANK_PROXIMITY_BM25;

    /**
     * Ranking mode expression (for SPH_RANK_EXPR)
     *
     * @var string
     */
    protected $rank_expr = '';

    /**
     * Max query time, milliseconds (0 means no limit)
     *
     * @var int
     */
    protected $max_query_time = 0;

    /**
     * Per-field-name weights
     *
     * @var array
     */
    protected $field_weights = array();

    /**
     * Per-query attribute values overrides
     *
     * @var array
     */
    protected $overrides = array();

    /**
     * Select-list (attributes or expressions, with optional aliases)
     *
     * @var string
     */
    protected $select = '*';

    /**
     * Per-query various flags
     *
     * @var int
     */
    protected $query_flags = 0;

    /**
     * Per-query max_predicted_time
     *
     * @var int
     */
    protected $predicted_time = 0;

    /**
     * Outer match sort by
     *
     * @var string
     */
    protected $outer_order_by = '';

    /**
     * Outer offset
     *
     * @var int
     */
    protected $outer_offset = 0;

    /**
     * Outer limit
     *
     * @var int
     */
    protected $outer_limit = 0;

    /**
     * @var bool
     */
    protected $has_outer = false;

    /**
     * Last error message
     *
     * @var string
     */
    protected $error = '';

    /**
     * Last warning message
     *
     * @var string
     */
    protected $warning = '';

    /**
     * Connection error vs remote error flag
     *
     * @var bool
     */
    protected $conn_error = false;

    /**
     * Requests array for multi-query
     *
     * @var array
     */
    protected $reqs = array();

    /**
     * Stored mbstring encoding
     *
     * @var string
     */
    protected $mbenc = '';

    /**
     * Whether $result['matches'] should be a hash or an array
     *
     * @var bool
     */
    protected $array_result = false;

    /**
     * Connect timeout
     *
     * @var int
     */
    protected $timeout = 0;

    /**
     * @var bool
     */
    protected $path = false;

    /**
     * @var resource|bool
     */
    protected $socket = false;

    /////////////////////////////////////////////////////////////////////////////
    // common stuff
    /////////////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        // default idf=tfidf_normalized
        $this->query_flags = sphSetBit(0, 6, true);
    }

    public function __destruct()
    {
        if ($this->socket !== false) {
            fclose($this->socket);
        }
    }

    /**
     * @return string
     */
    public function getLastError()
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getLastWarning()
    {
        return $this->warning;
    }

    /**
     * Get last error flag (to tell network connection errors from searchd errors or broken responses)
     *
     * @return bool
     */
    public function isConnectError()
    {
        return $this->conn_error;
    }

    /**
     * Set searchd host name and port
     *
     * @param string $host
     * @param int $port
     */
    public function setServer($host, $port = 0)
    {
        assert(is_string($host));
        if ($host[0] == '/') {
            $this->path = 'unix://' . $host;
            return;
        }
        if (substr($host, 0, 7) == 'unix://') {
            $this->path = $host;
            return;
        }

        $this->host = $host;
        $port = intval($port);
        assert(0 <= $port && $port < 65536);
        $this->port = $port == 0 ? 9312 : $port;
        $this->path = '';
    }

    /**
     * Set server connection timeout (0 to remove)
     *
     * @param int $timeout
     */
    public function setConnectTimeout($timeout)
    {
        assert(is_numeric($timeout));
        $this->timeout = $timeout;
    }

    /**
     * @param resource $handle
     * @param string $data
     * @param int $length
     *
     * @return bool
     */
    protected function send($handle, $data, $length)
    {
        if (feof($handle) || fwrite($handle, $data, $length) !== $length) {
            $this->error = 'connection unexpectedly closed (timed out?)';
            $this->conn_error = true;
            return false;
        }
        return true;
    }

    /////////////////////////////////////////////////////////////////////////////

    /**
     * Enter mbstring workaround mode
     */
    protected function mbPush()
    {
        $this->mbenc = '';
        if (ini_get('mbstring.func_overload') & 2) {
            $this->mbenc = mb_internal_encoding();
            mb_internal_encoding('latin1');
        }
    }

    /**
     * Leave mbstring workaround mode
     */
    protected function mbPop()
    {
        if ($this->mbenc) {
            mb_internal_encoding($this->mbenc);
        }
    }

    /**
     * Connect to searchd server
     *
     * @return bool|resource
     */
    protected function connect()
    {
        if ($this->socket !== false) {
            // we are in persistent connection mode, so we have a socket
            // however, need to check whether it's still alive
            if (!@feof($this->socket)) {
                return $this->socket;
            }

            // force reopen
            $this->socket = false;
        }

        $errno = 0;
        $errstr = '';
        $this->conn_error = false;

        if ($this->path) {
            $host = $this->path;
            $port = 0;
        } else {
            $host = $this->host;
            $port = $this->port;
        }

        if ($this->timeout <= 0) {
            $fp = @fsockopen($host, $port, $errno, $errstr);
        } else {
            $fp = @fsockopen($host, $port, $errno, $errstr, $this->timeout);
        }

        if (!$fp) {
            if ($this->path) {
                $location = $this->path;
            } else {
                $location = "{$this->host}:{$this->port}";
            }

            $errstr = trim($errstr);
            $this->error = "connection to $location failed (errno=$errno, msg=$errstr)";
            $this->conn_error = true;
            return false;
        }

        // send my version
        // this is a subtle part. we must do it before (!) reading back from searchd.
        // because otherwise under some conditions (reported on FreeBSD for instance)
        // TCP stack could throttle write-write-read pattern because of Nagle.
        if (!$this->send($fp, pack('N', 1), 4)) {
            fclose($fp);
            $this->error = 'failed to send client protocol version';
            return false;
        }

        // check version
        list(, $v) = unpack('N*', fread($fp, 4));
        $v = (int)$v;
        if ($v < 1) {
            fclose($fp);
            $this->error = "expected searchd protocol version 1+, got version '$v'";
            return false;
        }

        return $fp;
    }

    /**
     * Get and check response packet from searchd server
     *
     * @param resource $fp
     * @param int $client_ver
     *
     * @return bool|string
     */
    protected function getResponse($fp, $client_ver)
    {
        $response = '';
        $len = 0;

        $header = fread($fp, 8);
        if (strlen($header) == 8) {
            list($status, $ver, $len) = array_values(unpack('n2a/Nb', $header));
            $left = $len;
            while ($left > 0 && !feof($fp)) {
                $chunk = fread($fp, min(8192, $left));
                if ($chunk) {
                    $response .= $chunk;
                    $left -= strlen($chunk);
                }
            }
        }

        if ($this->socket === false) {
            fclose($fp);
        }

        // check response
        $read = strlen($response);
        if (!$response || $read != $len) {
            $this->error = $len
                ? "failed to read searchd response (status=$status, ver=$ver, len=$len, read=$read)"
                : 'received zero-sized searchd response';
            return false;
        }

        // check status
        if ($status == SEARCHD_WARNING) {
            list(, $wlen) = unpack('N*', substr($response, 0, 4));
            $this->warning = substr($response, 4, $wlen);
            return substr($response, 4 + $wlen);
        }
        if ($status == SEARCHD_ERROR) {
            $this->error = 'searchd error: ' . substr($response, 4);
            return false;
        }
        if ($status == SEARCHD_RETRY) {
            $this->error = 'temporary searchd error: ' . substr($response, 4);
            return false;
        }
        if ($status != SEARCHD_OK) {
            $this->error = "unknown status code '$status'";
            return false;
        }

        // check version
        if ($ver < $client_ver) {
            $this->warning = sprintf(
                'searchd command v.%d.%d older than client\'s v.%d.%d, some options might not work',
                $ver >> 8,
                $ver & 0xff,
                $client_ver >> 8,
                $client_ver & 0xff
            );
        }

        return $response;
    }

    /////////////////////////////////////////////////////////////////////////////
    // searching
    /////////////////////////////////////////////////////////////////////////////

    /**
     * Set offset and count into result set, and optionally set max-matches and cutoff limits
     *
     * @param int $offset
     * @param int $limit
     * @param int $max
     * @param int $cutoff
     */
    public function setLimits($offset, $limit, $max = 0, $cutoff = 0)
    {
        assert(is_int($offset));
        assert(is_int($limit));
        assert($offset >= 0);
        assert($limit > 0);
        assert($max >= 0);
        $this->offset = $offset;
        $this->limit = $limit;
        if ($max > 0) {
            $this->max_matches = $max;
        }
        if ($cutoff > 0) {
            $this->cutoff = $cutoff;
        }
    }

    /**
     * Set maximum query time, in milliseconds, per-index, 0 means 'do not limit'
     *
     * @param int $max
     */
    public function setMaxQueryTime($max)
    {
        assert(is_int($max));
        assert($max >= 0);
        $this->max_query_time = $max;
    }

    /**
     * Set matching mode
     *
     * @param int $mode
     */
    public function setMatchMode($mode)
    {
        trigger_error(
            'DEPRECATED: Do not call this method or, even better, use SphinxQL instead of an API',
            E_USER_DEPRECATED
        );
        assert(in_array($mode, array(
            SPH_MATCH_ALL,
            SPH_MATCH_ANY,
            SPH_MATCH_PHRASE,
            SPH_MATCH_BOOLEAN,
            SPH_MATCH_EXTENDED,
            SPH_MATCH_FULLSCAN,
            SPH_MATCH_EXTENDED2
        )));
        $this->mode = $mode;
    }

    /**
     * Set ranking mode
     *
     * @param int $ranker
     * @param string $rank_expr
     */
    public function setRankingMode($ranker, $rank_expr='')
    {
        assert($ranker === 0 || $ranker >= 1 && $ranker < SPH_RANK_TOTAL);
        assert(is_string($rank_expr));
        $this->ranker = $ranker;
        $this->rank_expr = $rank_expr;
    }

    /**
     * Set matches sorting mode
     *
     * @param int $mode
     * @param string $sort_by
     */
    public function setSortMode($mode, $sort_by = '')
    {
        assert(in_array($mode, array(
            SPH_SORT_RELEVANCE,
            SPH_SORT_ATTR_DESC,
            SPH_SORT_ATTR_ASC,
            SPH_SORT_TIME_SEGMENTS,
            SPH_SORT_EXTENDED,
            SPH_SORT_EXPR
        )));
        assert(is_string($sort_by));
        assert($mode == SPH_SORT_RELEVANCE || strlen($sort_by) > 0);

        $this->sort = $mode;
        $this->sort_by = $sort_by;
    }

    /**
     * Bind per-field weights by order
     *
     * @deprecated use setFieldWeights() instead
     */
    public function setWeights()
    {
        throw new \RuntimeException('This method is now deprecated; please use setFieldWeights instead');
    }

    /**
     * Bind per-field weights by name
     *
     * @param array $weights
     */
    public function setFieldWeights(array $weights)
    {
        foreach ($weights as $name => $weight) {
            assert(is_string($name));
            assert(is_int($weight));
        }
        $this->field_weights = $weights;
    }

    /**
     * Bind per-index weights by name
     *
     * @param array $weights
     */
    public function setIndexWeights(array $weights)
    {
        foreach ($weights as $index => $weight) {
            assert(is_string($index));
            assert(is_int($weight));
        }
        $this->index_weights = $weights;
    }

    /**
     * Set IDs range to match. Only match records if document ID is beetwen $min and $max (inclusive)
     *
     * @param int $min
     * @param int $max
     */
    public function setIDRange($min, $max)
    {
        assert(is_numeric($min));
        assert(is_numeric($max));
        assert($min <= $max);

        $this->min_id = $min;
        $this->max_id = $max;
    }

    /**
     * Set values set filter. Only match records where $attribute value is in given set
     *
     * @param string $attribute
     * @param array $values
     * @param bool $exclude
     */
    public function setFilter($attribute, array $values, $exclude = false)
    {
        assert(is_string($attribute));
        assert(count($values));

        foreach ($values as $value) {
            assert(is_numeric($value));
        }

        $this->filters[] = array(
            'type' => SPH_FILTER_VALUES,
            'attr' => $attribute,
            'exclude' => $exclude,
            'values' => $values
        );
    }

    /**
     * Set string filter
     * Only match records where $attribute value is equal
     *
     * @param string $attribute
     * @param string $value
     * @param bool $exclude
     */
    public function setFilterString($attribute, $value, $exclude = false)
    {
        assert(is_string($attribute));
        assert(is_string($value));
        $this->filters[] = array(
            'type' => SPH_FILTER_STRING,
            'attr' => $attribute,
            'exclude' => $exclude,
            'value' => $value
        );
    }    

    /**
     * Set range filter
     * Only match records if $attribute value is beetwen $min and $max (inclusive)
     *
     * @param string $attribute
     * @param int $min
     * @param int $max
     * @param bool $exclude
     */
    public function setFilterRange($attribute, $min, $max, $exclude = false)
    {
        assert(is_string($attribute));
        assert(is_numeric($min));
        assert(is_numeric($max));
        assert($min <= $max);

        $this->filters[] = array(
            'type' => SPH_FILTER_RANGE,
            'attr' => $attribute,
            'exclude' => $exclude,
            'min' => $min,
            'max' => $max
        );
    }

    /**
     * Set float range filter
     * Only match records if $attribute value is beetwen $min and $max (inclusive)
     *
     * @param string $attribute
     * @param int $min
     * @param int $max
     * @param bool $exclude
     */
    public function setFilterFloatRange($attribute, $min, $max, $exclude = false)
    {
        assert(is_string($attribute));
        assert(is_float($min));
        assert(is_float($max));
        assert($min <= $max);

        $this->filters[] = array(
            'type' => SPH_FILTER_FLOATRANGE,
            'attr' => $attribute,
            'exclude' => $exclude,
            'min' => $min,
            'max' => $max
        );
    }

    /**
     * Setup anchor point for geosphere distance calculations
     * Required to use @geodist in filters and sorting
     * Latitude and longitude must be in radians
     *
     * @param string $attr_lat
     * @param string $attr_long
     * @param float $lat
     * @param float $long
     */
    public function setGeoAnchor($attr_lat, $attr_long, $lat, $long)
    {
        assert(is_string($attr_lat));
        assert(is_string($attr_long));
        assert(is_float($lat));
        assert(is_float($long));

        $this->anchor = array(
            'attrlat' => $attr_lat,
            'attrlong' => $attr_long,
            'lat' => $lat,
            'long' => $long
        );
    }

    /**
     * Set grouping attribute and function
     *
     * @param string $attribute
     * @param string $func
     * @param string $group_sort
     */
    public function setGroupBy($attribute, $func, $group_sort = '@group desc')
    {
        assert(is_string($attribute));
        assert(is_string($group_sort));
        assert(in_array($func, array(
            SPH_GROUPBY_DAY,
            SPH_GROUPBY_WEEK,
            SPH_GROUPBY_MONTH,
            SPH_GROUPBY_YEAR,
            SPH_GROUPBY_ATTR,
            SPH_GROUPBY_ATTRPAIR
        )));

        $this->group_by = $attribute;
        $this->group_func = $func;
        $this->group_sort = $group_sort;
    }

    /**
     * Set count-distinct attribute for group-by queries
     *
     * @param string $attribute
     */
    public function setGroupDistinct($attribute)
    {
        assert(is_string($attribute));
        $this->group_distinct = $attribute;
    }

    /**
     * Set distributed retries count and delay
     *
     * @param int $count
     * @param int $delay
     */
    public function setRetries($count, $delay = 0)
    {
        assert(is_int($count) && $count >= 0);
        assert(is_int($delay) && $delay >= 0);
        $this->retry_count = $count;
        $this->retry_delay = $delay;
    }

    /**
     * Set result set format (hash or array; hash by default)
     * PHP specific; needed for group-by-MVA result sets that may contain duplicate IDs
     *
     * @param bool $array_result
     */
    public function setArrayResult($array_result)
    {
        assert(is_bool($array_result));
        $this->array_result = $array_result;
    }

    /**
     * Set attribute values override
     * There can be only one override per attribute
     * $values must be a hash that maps document IDs to attribute values
     *
     * @deprecated Do not call this method. Use SphinxQL REMAP() function instead.
     *
     * @param string $attr_name
     * @param string $attr_type
     * @param array $values
     */
    public function setOverride($attr_name, $attr_type, array $values)
    {
        trigger_error(
            'DEPRECATED: Do not call this method. Use SphinxQL REMAP() function instead.',
            E_USER_DEPRECATED
        );
        assert(is_string($attr_name));
        assert(in_array($attr_type, array(
            SPH_ATTR_INTEGER,
            SPH_ATTR_TIMESTAMP,
            SPH_ATTR_BOOL,
            SPH_ATTR_FLOAT,
            SPH_ATTR_BIGINT
        )));

        $this->overrides[$attr_name] = array(
            'attr' => $attr_name,
            'type' => $attr_type,
            'values' => $values
        );
    }

    /**
     * Set select-list (attributes or expressions), SQL-like syntax
     *
     * @param string $select
     */
    public function setSelect($select)
    {
        assert(is_string($select));
        $this->select = $select;
    }

    /**
     * @param string $flag_name
     * @param string|int $flag_value
     */
    public function setQueryFlag($flag_name, $flag_value)
    {
        $known_names = array(
            'reverse_scan',
            'sort_method',
            'max_predicted_time',
            'boolean_simplify',
            'idf',
            'global_idf',
            'low_priority'
        );
        $flags = array (
            'reverse_scan' => array(0, 1),
            'sort_method' => array('pq', 'kbuffer'),
            'max_predicted_time' => array(0),
            'boolean_simplify' => array(true, false),
            'idf' => array ('normalized', 'plain', 'tfidf_normalized', 'tfidf_unnormalized'),
            'global_idf' => array(true, false),
            'low_priority' => array(true, false)
        );

        assert(isset($flag_name, $known_names));
        assert(
            in_array($flag_value, $flags[$flag_name], true) ||
            ($flag_name == 'max_predicted_time' && is_int($flag_value) && $flag_value >= 0)
        );

        switch ($flag_name) {
            case 'reverse_scan':
                $this->query_flags = sphSetBit($this->query_flags, 0, $flag_value == 1);
                break;
            case 'sort_method':
                $this->query_flags = sphSetBit($this->query_flags, 1, $flag_value == 'kbuffer');
                break;
            case 'max_predicted_time':
                $this->query_flags = sphSetBit($this->query_flags, 2, $flag_value > 0);
                $this->predicted_time = (int)$flag_value;
                break;
            case 'boolean_simplify':
                $this->query_flags = sphSetBit($this->query_flags, 3, $flag_value);
                break;
            case 'idf':
                if ($flag_value == 'normalized' || $flag_value == 'plain') {
                    $this->query_flags = sphSetBit($this->query_flags, 4, $flag_value == 'plain');
                }
                if ($flag_value == 'tfidf_normalized' || $flag_value == 'tfidf_unnormalized') {
                    $this->query_flags = sphSetBit($this->query_flags, 6, $flag_value == 'tfidf_normalized');
                }
                break;
            case 'global_idf':
                $this->query_flags = sphSetBit($this->query_flags, 5, $flag_value);
                break;
            case 'low_priority':
                $this->query_flags = sphSetBit($this->query_flags, 8, $flag_value);
                break;
        }
    }

    /**
     * Set outer order by parameters
     *
     * @param string $order_by
     * @param int $offset
     * @param int $limit
     */
    public function setOuterSelect($order_by, $offset, $limit)
    {
        assert(is_string($order_by));
        assert(is_int($offset));
        assert(is_int($limit));
        assert($offset >= 0);
        assert($limit > 0);

        $this->outer_order_by = $order_by;
        $this->outer_offset = $offset;
        $this->outer_limit = $limit;
        $this->has_outer = true;
    }


    //////////////////////////////////////////////////////////////////////////////

    /**
     * Clear all filters (for multi-queries)
     */
    public function resetFilters()
    {
        $this->filters = array();
        $this->anchor = array();
    }

    /**
     * Clear groupby settings (for multi-queries)
     */
    public function resetGroupBy()
    {
        $this->group_by = '';
        $this->group_func = SPH_GROUPBY_DAY;
        $this->group_sort = '@group desc';
        $this->group_distinct = '';
    }

    /**
     * Clear all attribute value overrides (for multi-queries)
     */
    public function resetOverrides()
    {
        $this->overrides = array();
    }

    public function resetQueryFlag()
    {
        $this->query_flags = sphSetBit(0, 6, true); // default idf=tfidf_normalized
        $this->predicted_time = 0;
    }

    public function resetOuterSelect()
    {
        $this->outer_order_by = '';
        $this->outer_offset = 0;
        $this->outer_limit = 0;
        $this->has_outer = false;
    }

    //////////////////////////////////////////////////////////////////////////////

    /**
     * Connect to searchd server, run given search query through given indexes, and return the search results
     *
     * @param string  $query
     * @param string $index
     * @param string $comment
     *
     * @return bool
     */
    public function query($query, $index = '*', $comment = '')
    {
        assert(empty($this->reqs));

        $this->addQuery($query, $index, $comment);
        $results = $this->runQueries();
        $this->reqs = array(); // just in case it failed too early

        if (!is_array($results)) {
            return false; // probably network error; error message should be already filled
        }

        $this->error = $results[0]['error'];
        $this->warning = $results[0]['warning'];

        if ($results[0]['status'] == SEARCHD_ERROR) {
            return false;
        } else {
            return $results[0];
        }
    }

    /**
     * Helper to pack floats in network byte order
     *
     * @param float $float
     *
     * @return string
     */
    protected function packFloat($float)
    {
        $t1 = pack('f', $float); // machine order
        list(, $t2) = unpack('L*', $t1); // int in machine order
        return pack('N', $t2);
    }

    /**
     * Add query to multi-query batch
     * Returns index into results array from RunQueries() call
     *
     * @param string $query
     * @param string $index
     * @param string $comment
     *
     * @return int
     */
    public function addQuery($query, $index = '*', $comment = '')
    {
        // mbstring workaround
        $this->mbPush();

        // build request
        $req = pack('NNNNN', $this->query_flags, $this->offset, $this->limit, $this->mode, $this->ranker);
        if ($this->ranker == SPH_RANK_EXPR) {
            $req .= pack('N', strlen($this->rank_expr)) . $this->rank_expr;
        }
        $req .= pack('N', $this->sort); // (deprecated) sort mode
        $req .= pack('N', strlen($this->sort_by)) . $this->sort_by;
        $req .= pack('N', strlen($query)) . $query; // query itself
        $req .= pack('N', count($this->weights)); // weights
        foreach ($this->weights as $weight) {
            $req .= pack('N', (int)$weight);
        }
        $req .= pack('N', strlen($index)) . $index; // indexes
        $req .= pack('N', 1); // id64 range marker
        $req .= sphPackU64($this->min_id) . sphPackU64($this->max_id); // id64 range

        // filters
        $req .= pack('N', count($this->filters));
        foreach ($this->filters as $filter) {
            $req .= pack('N', strlen($filter['attr'])) . $filter['attr'];
            $req .= pack('N', $filter['type']);
            switch ($filter['type']) {
                case SPH_FILTER_VALUES:
                    $req .= pack('N', count($filter['values']));
                    foreach ($filter['values'] as $value) {
                        $req .= sphPackI64($value);
                    }
                    break;
                case SPH_FILTER_RANGE:
                    $req .= sphPackI64($filter['min']) . sphPackI64($filter['max']);
                    break;
                case SPH_FILTER_FLOATRANGE:
                    $req .= $this->packFloat($filter['min']) . $this->packFloat($filter['max']);
                    break;
                case SPH_FILTER_STRING:
                    $req .= pack('N', strlen($filter['value'])) . $filter['value'];
                    break;
                default:
                    assert(0 && 'internal error: unhandled filter type');
            }
            $req .= pack('N', $filter['exclude']);
        }

        // group-by clause, max-matches count, group-sort clause, cutoff count
        $req .= pack('NN', $this->group_func, strlen($this->group_by)) . $this->group_by;
        $req .= pack('N', $this->max_matches);
        $req .= pack('N', strlen($this->group_sort)) . $this->group_sort;
        $req .= pack('NNN', $this->cutoff, $this->retry_count, $this->retry_delay);
        $req .= pack('N', strlen($this->group_distinct)) . $this->group_distinct;

        // anchor point
        if (empty($this->anchor)) {
            $req .= pack('N', 0);
        } else {
            $a =& $this->anchor;
            $req .= pack('N', 1);
            $req .= pack('N', strlen($a['attrlat'])) . $a['attrlat'];
            $req .= pack('N', strlen($a['attrlong'])) . $a['attrlong'];
            $req .= $this->packFloat($a['lat']) . $this->packFloat($a['long']);
        }

        // per-index weights
        $req .= pack('N', count($this->index_weights));
        foreach ($this->index_weights as $idx => $weight) {
            $req .= pack('N', strlen($idx)) . $idx . pack('N', $weight);
        }

        // max query time
        $req .= pack('N', $this->max_query_time);

        // per-field weights
        $req .= pack('N', count($this->field_weights));
        foreach ($this->field_weights as $field => $weight) {
            $req .= pack('N', strlen($field)) . $field . pack('N', $weight);
        }

        // comment
        $req .= pack('N', strlen($comment)) . $comment;

        // attribute overrides
        $req .= pack('N', count($this->overrides));
        foreach ($this->overrides as $key => $entry) {
            $req .= pack('N', strlen($entry['attr'])) . $entry['attr'];
            $req .= pack('NN', $entry['type'], count($entry['values']));
            foreach ($entry['values'] as $id => $val) {
                assert(is_numeric($id));
                assert(is_numeric($val));

                $req .= sphPackU64($id);
                switch ($entry['type']) {
                    case SPH_ATTR_FLOAT:
                        $req .= $this->packFloat($val);
                        break;
                    case SPH_ATTR_BIGINT:
                        $req .= sphPackI64($val);
                        break;
                    default:
                        $req .= pack('N', $val);
                        break;
                }
            }
        }

        // select-list
        $req .= pack('N', strlen($this->select)) . $this->select;

        // max_predicted_time
        if ($this->predicted_time > 0) {
            $req .= pack('N', (int)$this->predicted_time);
        }

        $req .= pack('N', strlen($this->outer_order_by)) . $this->outer_order_by;
        $req .= pack('NN', $this->outer_offset, $this->outer_limit);
        if ($this->has_outer) {
            $req .= pack('N', 1);
        } else {
            $req .= pack('N', 0);
        }

        // mbstring workaround
        $this->mbPop();

        // store request to requests array
        $this->reqs[] = $req;
        return count($this->reqs) - 1;
    }

    /**
     * Connect to searchd, run queries batch, and return an array of result sets
     *
     * @return array|bool
     */
    public function runQueries()
    {
        if (empty($this->reqs)) {
            $this->error = 'no queries defined, issue AddQuery() first';
            return false;
        }

        // mbstring workaround
        $this->mbPush();

        if (!($fp = $this->connect())) {
            $this->mbPop();
            return false;
        }

        // send query, get response
        $nreqs = count($this->reqs);
        $req = join('', $this->reqs);
        $len = 8 + strlen($req);
        $req = pack('nnNNN', SEARCHD_COMMAND_SEARCH, VER_COMMAND_SEARCH, $len, 0, $nreqs) . $req; // add header

        if (!$this->send($fp, $req, $len + 8) || !($response = $this->getResponse($fp, VER_COMMAND_SEARCH))) {
            $this->mbPop();
            return false;
        }

        // query sent ok; we can reset reqs now
        $this->reqs = array();

        // parse and return response
        return $this->parseSearchResponse($response, $nreqs);
    }

    /**
     * Parse and return search query (or queries) response
     *
     * @param string $response
     * @param int $nreqs
     *
     * @return array
     */
    protected function parseSearchResponse($response, $nreqs)
    {
        $p = 0; // current position
        $max = strlen($response); // max position for checks, to protect against broken responses

        $results = array();
        for ($ires = 0; $ires < $nreqs && $p < $max; $ires++) {
            $results[] = array();
            $result =& $results[$ires];

            $result['error'] = '';
            $result['warning'] = '';

            // extract status
            list(, $status) = unpack('N*', substr($response, $p, 4));
            $p += 4;
            $result['status'] = $status;
            if ($status != SEARCHD_OK) {
                list(, $len) = unpack('N*', substr($response, $p, 4));
                $p += 4;
                $message = substr($response, $p, $len);
                $p += $len;

                if ($status == SEARCHD_WARNING) {
                    $result['warning'] = $message;
                } else {
                    $result['error'] = $message;
                    continue;
                }
            }

            // read schema
            $fields = array();
            $attrs = array();

            list(, $nfields) = unpack('N*', substr($response, $p, 4));
            $p += 4;
            while ($nfields --> 0 && $p < $max) {
                list(, $len) = unpack('N*', substr($response, $p, 4));
                $p += 4;
                $fields[] = substr($response, $p, $len);
                $p += $len;
            }
            $result['fields'] = $fields;

            list(, $nattrs) = unpack('N*', substr($response, $p, 4));
            $p += 4;
            while ($nattrs --> 0 && $p < $max) {
                list(, $len) = unpack('N*', substr($response, $p, 4));
                $p += 4;
                $attr = substr($response, $p, $len);
                $p += $len;
                list(, $type) = unpack('N*', substr($response, $p, 4));
                $p += 4;
                $attrs[$attr] = $type;
            }
            $result['attrs'] = $attrs;

            // read match count
            list(, $count) = unpack('N*', substr($response, $p, 4));
            $p += 4;
            list(, $id64) = unpack('N*', substr($response, $p, 4));
            $p += 4;

            // read matches
            $idx = -1;
            while ($count --> 0 && $p < $max) {
                // index into result array
                $idx++;

                // parse document id and weight
                if ($id64) {
                    $doc = sphUnpackU64(substr($response, $p, 8));
                    $p += 8;
                    list(,$weight) = unpack('N*', substr($response, $p, 4));
                    $p += 4;
                } else {
                    list($doc, $weight) = array_values(unpack('N*N*', substr($response, $p, 8)));
                    $p += 8;
                    $doc = sphFixUint($doc);
                }
                $weight = sprintf('%u', $weight);

                // create match entry
                if ($this->array_result) {
                    $result['matches'][$idx] = array('id' => $doc, 'weight' => $weight);
                } else {
                    $result['matches'][$doc]['weight'] = $weight;
                }

                // parse and create attributes
                $attrvals = array();
                foreach ($attrs as $attr => $type) {
                    // handle 64bit ints
                    if ($type == SPH_ATTR_BIGINT) {
                        $attrvals[$attr] = sphUnpackI64(substr($response, $p, 8));
                        $p += 8;
                        continue;
                    }

                    // handle floats
                    if ($type == SPH_ATTR_FLOAT) {
                        list(, $uval) = unpack('N*', substr($response, $p, 4));
                        $p += 4;
                        list(, $fval) = unpack('f*', pack('L', $uval));
                        $attrvals[$attr] = $fval;
                        continue;
                    }

                    // handle everything else as unsigned ints
                    list(, $val) = unpack('N*', substr($response, $p, 4));
                    $p += 4;
                    if ($type == SPH_ATTR_MULTI) {
                        $attrvals[$attr] = array();
                        $nvalues = $val;
                        while ($nvalues --> 0 && $p < $max) {
                            list(, $val) = unpack('N*', substr($response, $p, 4));
                            $p += 4;
                            $attrvals[$attr][] = sphFixUint($val);
                        }
                    } elseif ($type == SPH_ATTR_MULTI64) {
                        $attrvals[$attr] = array();
                        $nvalues = $val;
                        while ($nvalues > 0 && $p < $max) {
                            $attrvals[$attr][] = sphUnpackI64(substr($response, $p, 8));
                            $p += 8;
                            $nvalues -= 2;
                        }
                    } elseif ($type == SPH_ATTR_STRING) {
                        $attrvals[$attr] = substr($response, $p, $val);
                        $p += $val;
                    } elseif ($type == SPH_ATTR_FACTORS) {
                        $attrvals[$attr] = substr($response, $p, $val - 4);
                        $p += $val-4;
                    } else {
                        $attrvals[$attr] = sphFixUint($val);
                    }
                }

                if ($this->array_result) {
                    $result['matches'][$idx]['attrs'] = $attrvals;
                } else {
                    $result['matches'][$doc]['attrs'] = $attrvals;
                }
            }

            list($total, $total_found, $msecs, $words) = array_values(unpack('N*N*N*N*', substr($response, $p, 16)));
            $result['total'] = sprintf('%u', $total);
            $result['total_found'] = sprintf('%u', $total_found);
            $result['time'] = sprintf('%.3f', $msecs / 1000);
            $p += 16;

            while ($words --> 0 && $p < $max) {
                list(, $len) = unpack('N*', substr($response, $p, 4));
                $p += 4;
                $word = substr($response, $p, $len);
                $p += $len;
                list($docs, $hits) = array_values(unpack('N*N*', substr($response, $p, 8)));
                $p += 8;
                $result['words'][$word] = array (
                    'docs' => sprintf('%u', $docs),
                    'hits' => sprintf('%u', $hits)
                );
            }
        }

        $this->mbPop();
        return $results;
    }

    /////////////////////////////////////////////////////////////////////////////
    // excerpts generation
    /////////////////////////////////////////////////////////////////////////////

    /**
     * Connect to searchd server, and generate exceprts (snippets) of given documents for given query.
     * Returns false on failure, an array of snippets on success
     *
     * @param array $docs
     * @param string $index
     * @param string $words
     * @param array $opts
     *
     * @return array|bool
     */
    public function buildExcerpts(array $docs, $index, $words, array $opts = array())
    {
        assert(is_string($index));
        assert(is_string($words));

        $this->mbPush();

        if (!($fp = $this->connect())) {
            $this->mbPop();
            return false;
        }

        /////////////////
        // fixup options
        /////////////////

        $opts = array_merge(array(
            'before_match' => '<b>',
            'after_match' => '</b>',
            'chunk_separator' => ' ... ',
            'limit' => 256,
            'limit_passages' => 0,
            'limit_words' => 0,
            'around' => 5,
            'exact_phrase' => false,
            'single_passage' => false,
            'use_boundaries' => false,
            'weight_order' => false,
            'query_mode' => false,
            'force_all_words' => false,
            'start_passage_id' => 1,
            'load_files' => false,
            'html_strip_mode' => 'index',
            'allow_empty' => false,
            'passage_boundary' => 'none',
            'emit_zones' => false,
            'load_files_scattered' => false
        ), $opts);

        /////////////////
        // build request
        /////////////////

        // v.1.2 req
        $flags = 1; // remove spaces
        if ($opts['exact_phrase']) {
            $flags |= 2;
        }
        if ($opts['single_passage']) {
            $flags |= 4;
        }
        if ($opts['use_boundaries']) {
            $flags |= 8;
        }
        if ($opts['weight_order']) {
            $flags |= 16;
        }
        if ($opts['query_mode']) {
            $flags |= 32;
        }
        if ($opts['force_all_words']) {
            $flags |= 64;
        }
        if ($opts['load_files']) {
            $flags |= 128;
        }
        if ($opts['allow_empty']) {
            $flags |= 256;
        }
        if ($opts['emit_zones']) {
            $flags |= 512;
        }
        if ($opts['load_files_scattered']) {
            $flags |= 1024;
        }
        $req = pack('NN', 0, $flags); // mode=0, flags=$flags
        $req .= pack('N', strlen($index)) . $index; // req index
        $req .= pack('N', strlen($words)) . $words; // req words

        // options
        $req .= pack('N', strlen($opts['before_match'])) . $opts['before_match'];
        $req .= pack('N', strlen($opts['after_match'])) . $opts['after_match'];
        $req .= pack('N', strlen($opts['chunk_separator'])) . $opts['chunk_separator'];
        $req .= pack('NN', (int)$opts['limit'], (int)$opts['around']);
        $req .= pack('NNN', (int)$opts['limit_passages'], (int)$opts['limit_words'], (int)$opts['start_passage_id']); // v.1.2
        $req .= pack('N', strlen($opts['html_strip_mode'])) . $opts['html_strip_mode'];
        $req .= pack('N', strlen($opts['passage_boundary'])) . $opts['passage_boundary'];

        // documents
        $req .= pack('N', count($docs));
        foreach ($docs as $doc) {
            assert(is_string($doc));
            $req .= pack('N', strlen($doc)) . $doc;
        }

        ////////////////////////////
        // send query, get response
        ////////////////////////////

        $len = strlen($req);
        $req = pack('nnN', SEARCHD_COMMAND_EXCERPT, VER_COMMAND_EXCERPT, $len) . $req; // add header
        if (!$this->send($fp, $req, $len + 8) || !($response = $this->getResponse($fp, VER_COMMAND_EXCERPT))) {
            $this->mbPop();
            return false;
        }

        //////////////////
        // parse response
        //////////////////

        $pos = 0;
        $res = array();
        $rlen = strlen($response);
        $count = count($docs);
        while ($count--) {
            list(, $len) = unpack('N*', substr($response, $pos, 4));
            $pos += 4;

            if ($pos + $len > $rlen) {
                $this->error = 'incomplete reply';
                $this->mbPop();
                return false;
            }
            $res[] = $len ? substr($response, $pos, $len) : '';
            $pos += $len;
        }

        $this->mbPop();
        return $res;
    }


    /////////////////////////////////////////////////////////////////////////////
    // keyword generation
    /////////////////////////////////////////////////////////////////////////////

    /**
     * Connect to searchd server, and generate keyword list for a given query returns false on failure,
     * an array of words on success
     *
     * @param string $query
     * @param string $index
     * @param bool $hits
     *
     * @return array|bool
     */
    public function buildKeywords($query, $index, $hits)
    {
        assert(is_string($query));
        assert(is_string($index));
        assert(is_bool($hits));

        $this->mbPush();

        if (!($fp = $this->connect())) {
            $this->mbPop();
            return false;
        }

        /////////////////
        // build request
        /////////////////

        // v.1.0 req
        $req  = pack('N', strlen($query)) . $query; // req query
        $req .= pack('N', strlen($index)) . $index; // req index
        $req .= pack('N', (int)$hits);

        ////////////////////////////
        // send query, get response
        ////////////////////////////

        $len = strlen($req);
        $req = pack('nnN', SEARCHD_COMMAND_KEYWORDS, VER_COMMAND_KEYWORDS, $len) . $req; // add header
        if (!$this->send($fp, $req, $len + 8) || !($response = $this->getResponse($fp, VER_COMMAND_KEYWORDS))) {
            $this->mbPop();
            return false;
        }

        //////////////////
        // parse response
        //////////////////

        $pos = 0;
        $res = array();
        $rlen = strlen($response);
        list(, $nwords) = unpack('N*', substr($response, $pos, 4));
        $pos += 4;
        for ($i = 0; $i < $nwords; $i++) {
            list(, $len) = unpack('N*', substr($response, $pos, 4));
            $pos += 4;
            $tokenized = $len ? substr($response, $pos, $len) : '';
            $pos += $len;

            list(, $len) = unpack('N*', substr($response, $pos, 4));
            $pos += 4;
            $normalized = $len ? substr($response, $pos, $len) : '';
            $pos += $len;

            $res[] = array(
                'tokenized' => $tokenized,
                'normalized' => $normalized
            );

            if ($hits) {
                list($ndocs, $nhits) = array_values(unpack('N*N*', substr($response, $pos, 8)));
                $pos += 8;
                $res[$i]['docs'] = $ndocs;
                $res[$i]['hits'] = $nhits;
            }

            if ($pos > $rlen) {
                $this->error = 'incomplete reply';
                $this->mbPop();
                return false;
            }
        }

        $this->mbPop();
        return $res;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function escapeString($string)
    {
        $from = array('\\', '(',')','|','-','!','@','~','"','&', '/', '^', '$', '=', '<');
        $to   = array('\\\\', '\(','\)','\|','\-','\!','\@','\~','\"', '\&', '\/', '\^', '\$', '\=', '\<');

        return str_replace($from, $to, $string);
    }

    /////////////////////////////////////////////////////////////////////////////
    // attribute updates
    /////////////////////////////////////////////////////////////////////////////

    /**
     * Batch update given attributes in given rows in given indexes
     * Returns amount of updated documents (0 or more) on success, or -1 on failure
     *
     * @param string $index
     * @param array $attrs
     * @param array $values
     * @param bool $mva
     * @param bool $ignore_non_existent
     *
     * @return int
     */
    public function updateAttributes($index, array $attrs, array $values, $mva = false, $ignore_non_existent = false)
    {
        // verify everything
        assert(is_string($index));
        assert(is_bool($mva));
        assert(is_bool($ignore_non_existent));

        foreach ($attrs as $attr) {
            assert(is_string($attr));
        }

        foreach ($values as $id => $entry) {
            assert(is_numeric($id));
            assert(is_array($entry));
            assert(count($entry) == count($attrs));
            foreach ($entry as $v) {
                if ($mva) {
                    assert(is_array($v));
                    foreach ($v as $vv) {
                        assert(is_int($vv));
                    }
                } else {
                    assert(is_int($v));
                }
            }
        }

        // build request
        $this->mbPush();
        $req = pack('N', strlen($index)) . $index;

        $req .= pack('N', count($attrs));
        $req .= pack('N', $ignore_non_existent ? 1 : 0);
        foreach ($attrs as $attr) {
            $req .= pack('N', strlen($attr)) . $attr;
            $req .= pack('N', $mva ? 1 : 0);
        }

        $req .= pack('N', count($values));
        foreach ($values as $id => $entry) {
            $req .= sphPackU64($id);
            foreach ($entry as $v) {
                $req .= pack('N', $mva ? count($v) : $v);
                if ($mva) {
                    foreach ($v as $vv) {
                        $req .= pack('N', $vv);
                    }
                }
            }
        }

        // connect, send query, get response
        if (!($fp = $this->connect())) {
            $this->mbPop();
            return -1;
        }

        $len = strlen($req);
        $req = pack('nnN', SEARCHD_COMMAND_UPDATE, VER_COMMAND_UPDATE, $len) . $req; // add header
        if (!$this->send($fp, $req, $len + 8)) {
            $this->mbPop();
            return -1;
        }

        if (!($response = $this->getResponse($fp, VER_COMMAND_UPDATE))) {
            $this->mbPop();
            return -1;
        }

        // parse response
        list(, $updated) = unpack('N*', substr($response, 0, 4));
        $this->mbPop();
        return $updated;
    }

    /////////////////////////////////////////////////////////////////////////////
    // persistent connections
    /////////////////////////////////////////////////////////////////////////////

    /**
     * @return bool
     */
    public function open()
    {
        if ($this->socket !== false) {
            $this->error = 'already connected';
            return false;
        }
        if (!($fp = $this->connect()))
            return false;

        // command, command version = 0, body length = 4, body = 1
        $req = pack('nnNN', SEARCHD_COMMAND_PERSIST, 0, 4, 1);
        if (!$this->send($fp, $req, 12)) {
            return false;
        }

        $this->socket = $fp;
        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        if ($this->socket === false) {
            $this->error = 'not connected';
            return false;
        }

        fclose($this->socket);
        $this->socket = false;

        return true;
    }

    //////////////////////////////////////////////////////////////////////////
    // status
    //////////////////////////////////////////////////////////////////////////

    /**
     * @param bool $session
     *
     * @return array|bool
     */
    public function status($session = false)
    {
        assert(is_bool($session));

        $this->mbPush();
        if (!($fp = $this->connect())) {
            $this->mbPop();
            return false;
        }

        $req = pack('nnNN', SEARCHD_COMMAND_STATUS, VER_COMMAND_STATUS, 4, $session ? 0 : 1); // len=4, body=1
        if (!$this->send($fp, $req, 12) || !($response = $this->getResponse($fp, VER_COMMAND_STATUS))) {
            $this->mbPop();
            return false;
        }

        $res = substr($response, 4); // just ignore length, error handling, etc
        $p = 0;
        list($rows, $cols) = array_values(unpack('N*N*', substr($response, $p, 8)));
        $p += 8;

        $res = array();
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                list(, $len) = unpack('N*', substr($response, $p, 4));
                $p += 4;
                $res[$i][] = substr($response, $p, $len);
                $p += $len;
            }
        }

        $this->mbPop();
        return $res;
    }

    //////////////////////////////////////////////////////////////////////////
    // flush
    //////////////////////////////////////////////////////////////////////////

    /**
     * @return int
     */
    public function flushAttributes()
    {
        $this->mbPush();
        if (!($fp = $this->connect())) {
            $this->mbPop();
            return -1;
        }

        $req = pack('nnN', SEARCHD_COMMAND_FLUSHATTRS, VER_COMMAND_FLUSHATTRS, 0); // len=0
        if (!$this->send($fp, $req, 8) || !($response = $this->getResponse($fp, VER_COMMAND_FLUSHATTRS))) {
            $this->mbPop();
            return -1;
        }

        $tag = -1;
        if (strlen($response) == 4) {
            list(, $tag) = unpack('N*', $response);
        } else {
            $this->error = 'unexpected response length';
        }

        $this->mbPop();
        return $tag;
    }
}
