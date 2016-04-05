<?php

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
