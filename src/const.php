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

// known searchd commands
/* @deprecated use Client::SEARCHD_COMMAND_SEARCH */
define('SEARCHD_COMMAND_SEARCH',     0);
/* @deprecated use Client::SEARCHD_COMMAND_EXCERPT */
define('SEARCHD_COMMAND_EXCERPT',    1);
/* @deprecated use Client::SEARCHD_COMMAND_UPDATE */
define('SEARCHD_COMMAND_UPDATE',     2);
/* @deprecated use Client::SEARCHD_COMMAND_KEYWORDS */
define('SEARCHD_COMMAND_KEYWORDS',   3);
/* @deprecated use Client::SEARCHD_COMMAND_PERSIST */
define('SEARCHD_COMMAND_PERSIST',    4);
/* @deprecated use Client::SEARCHD_COMMAND_STATUS */
define('SEARCHD_COMMAND_STATUS',     5);
/* @deprecated use Client::SEARCHD_COMMAND_FLUSHATTRS */
define('SEARCHD_COMMAND_FLUSHATTRS', 7);

// current client-side command implementation versions
/* @deprecated use Client::VER_COMMAND_SEARCH */
define('VER_COMMAND_SEARCH',     0x11E);
/* @deprecated use Client::VER_COMMAND_EXCERPT */
define('VER_COMMAND_EXCERPT',    0x104);
/* @deprecated use Client::VER_COMMAND_UPDATE */
define('VER_COMMAND_UPDATE',     0x103);
/* @deprecated use Client::VER_COMMAND_KEYWORDS */
define('VER_COMMAND_KEYWORDS',   0x100);
/* @deprecated use Client::VER_COMMAND_STATUS */
define('VER_COMMAND_STATUS',     0x101);
/* @deprecated use Client::VER_COMMAND_QUERY */
define('VER_COMMAND_QUERY',      0x100);
/* @deprecated use Client::VER_COMMAND_FLUSH_ATTRS */
define('VER_COMMAND_FLUSHATTRS', 0x100);

// known searchd status codes
/* @deprecated use Client::SEARCHD_OK */
define('SEARCHD_OK',      0);
/* @deprecated use Client::SEARCHD_ERROR */
define('SEARCHD_ERROR',   1);
/* @deprecated use Client::SEARCHD_RETRY */
define('SEARCHD_RETRY',   2);
/* @deprecated use Client::SEARCHD_WARNING */
define('SEARCHD_WARNING', 3);

// known match modes
/* @deprecated use Client::MATCH_ALL */
define('SPH_MATCH_ALL',       0);
/* @deprecated use Client::MATCH_ANY */
define('SPH_MATCH_ANY',       1);
/* @deprecated use Client::MATCH_PHRASE */
define('SPH_MATCH_PHRASE',    2);
/* @deprecated use Client::MATCH_BOOLEAN */
define('SPH_MATCH_BOOLEAN',   3);
/* @deprecated use Client::MATCH_EXTENDED */
define('SPH_MATCH_EXTENDED',  4);
/* @deprecated use Client::MATCH_FULL_SCAN */
define('SPH_MATCH_FULLSCAN',  5);
/* @deprecated use Client::MATCH_EXTENDED2 */
define('SPH_MATCH_EXTENDED2', 6); // extended engine V2 (TEMPORARY, WILL BE REMOVED)

// known ranking modes (ext2 only)
/* @deprecated use Client::RANK_PROXIMITY_BM25 */
define('SPH_RANK_PROXIMITY_BM25', 0); // default mode, phrase proximity major factor and BM25 minor one
/* @deprecated use Client::RANK_BM25 */
define('SPH_RANK_BM25',           1); // statistical mode, BM25 ranking only (faster but worse quality)
/* @deprecated use Client::RANK_NONE */
define('SPH_RANK_NONE',           2); // no ranking, all matches get a weight of 1
/* @deprecated use Client::RANK_WORD_COUNT */
define('SPH_RANK_WORDCOUNT',      3); // simple word-count weighting, rank is a weighted sum of per-field keyword occurence counts
/* @deprecated use Client::RANK_PROXIMITY */
define('SPH_RANK_PROXIMITY',      4);
/* @deprecated use Client::RANK_MATCH_ANY */
define('SPH_RANK_MATCHANY',       5);
/* @deprecated use Client::RANK_FIELD_MASK */
define('SPH_RANK_FIELDMASK',      6);
/* @deprecated use Client::RANK_SPH04 */
define('SPH_RANK_SPH04',          7);
/* @deprecated use Client::RANK_EXPR */
define('SPH_RANK_EXPR',           8);
/* @deprecated use Client::RANK_TOTAL */
define('SPH_RANK_TOTAL',          9);

// known sort modes
/* @deprecated use Client::SORT_RELEVANCE */
define('SPH_SORT_RELEVANCE',     0);
/* @deprecated use Client::SORT_ATTR_DESC */
define('SPH_SORT_ATTR_DESC',     1);
/* @deprecated use Client::SORT_ATTR_ASC */
define('SPH_SORT_ATTR_ASC',      2);
/* @deprecated use Client::SORT_TIME_SEGMENTS */
define('SPH_SORT_TIME_SEGMENTS', 3);
/* @deprecated use Client::SORT_EXTENDED */
define('SPH_SORT_EXTENDED',      4);
/* @deprecated use Client::SORT_EXPR */
define('SPH_SORT_EXPR',          5);

// known filter types
/* @deprecated use Client::FILTER_VALUES */
define('SPH_FILTER_VALUES',     0);
/* @deprecated use Client::FILTER_RANGE */
define('SPH_FILTER_RANGE',      1);
/* @deprecated use Client::FILTER_FLOAT_RANGE */
define('SPH_FILTER_FLOATRANGE', 2);
/* @deprecated use Client::FILTER_STRING */
define('SPH_FILTER_STRING',     3);

// known attribute types
/* @deprecated use Client::ATTR_INTEGER */
define('SPH_ATTR_INTEGER',   1);
/* @deprecated use Client::ATTR_TIMESTAMP */
define('SPH_ATTR_TIMESTAMP', 2);
/* @deprecated use Client::ATTR_ORDINAL */
define('SPH_ATTR_ORDINAL',   3);
/* @deprecated use Client::ATTR_BOOL */
define('SPH_ATTR_BOOL',      4);
/* @deprecated use Client::ATTR_FLOAT */
define('SPH_ATTR_FLOAT',     5);
/* @deprecated use Client::ATTR_BIGINT */
define('SPH_ATTR_BIGINT',    6);
/* @deprecated use Client::ATTR_STRING */
define('SPH_ATTR_STRING',    7);
/* @deprecated use Client::ATTR_FACTORS */
define('SPH_ATTR_FACTORS',   1001);
/* @deprecated use Client::ATTR_MULTI */
define('SPH_ATTR_MULTI',     0x40000001);
/* @deprecated use Client::ATTR_MULTI64 */
define('SPH_ATTR_MULTI64',   0x40000002);

// known grouping functions
/* @deprecated use Client::GROUP_BY_DAY */
define('SPH_GROUPBY_DAY',      0);
/* @deprecated use Client::GROUP_BY_WEEK */
define('SPH_GROUPBY_WEEK',     1);
/* @deprecated use Client::GROUP_BY_MONTH */
define('SPH_GROUPBY_MONTH',    2);
/* @deprecated use Client::GROUP_BY_YEAR */
define('SPH_GROUPBY_YEAR',     3);
/* @deprecated use Client::GROUP_BY_ATTR */
define('SPH_GROUPBY_ATTR',     4);
/* @deprecated use Client::GROUP_BY_ATTR_PAIR */
define('SPH_GROUPBY_ATTRPAIR', 5);
