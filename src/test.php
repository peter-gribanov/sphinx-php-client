<?php

//
// $Id$
//

$file = __DIR__.'/../vendor/autoload.php';

//////////////////////
// parse command line
//////////////////////

// for very old PHP versions, like at my home test server
if (is_array($argv) && !isset($_SERVER["argv"])) {
	$_SERVER["argv"] = $argv;
}
unset($_SERVER["argv"][0]);

// build query
if (!is_array($_SERVER["argv"]) || empty($_SERVER["argv"])) {
	print("Usage: php -f test.php [OPTIONS] query words\n\n");
	print("Options are:\n");
	print("-h, --host <HOST>\tconnect to searchd at host HOST\n");
	print("-p, --port\t\tconnect to searchd at port PORT\n");
	print("-i, --index <IDX>\tsearch through index(es) specified by IDX\n");
	print("-s, --sortby <CLAUSE>\tsort matches by 'CLAUSE' in sort_extended mode\n");
	print("-S, --sortexpr <EXPR>\tsort matches by 'EXPR' DESC in sort_expr mode\n");
	print("-a, --any\t\tuse 'match any word' matching mode\n");
	print("-b, --boolean\t\tuse 'boolean query' matching mode\n");
	print("-e, --extended\t\tuse 'extended query' matching mode\n");
	print("-ph,--phrase\t\tuse 'exact phrase' matching mode\n");
	print("-f, --filter <ATTR>\tfilter by attribute 'ATTR' (default is 'group_id')\n");
	print("-fr,--filterrange <ATTR> <MIN> <MAX>\n\t\t\tadd specified range filter\n");
	print("-v, --value <VAL>\tadd VAL to allowed 'group_id' values list\n");
	print("-g, --groupby <EXPR>\tgroup matches by 'EXPR'\n");
	print("-gs,--groupsort <EXPR>\tsort groups by 'EXPR'\n");
	print("-d, --distinct <ATTR>\tcount distinct values of 'ATTR''\n");
	print("-l, --limit <COUNT>\tretrieve COUNT matches (default: 20)\n");
	print("--select <EXPRLIST>\tuse 'EXPRLIST' as select-list (default: *)\n");
	exit;
}

$args = array();
foreach ($_SERVER["argv"] as $arg) {
	$args[] = $arg;
}

$cl = new SphinxClient();

$q = "";
$sql = "";
$mode = SPH_MATCH_ALL;
$host = "localhost";
$port = 9312;
$index = "*";
$groupby = "";
$groupsort = "@group desc";
$filter = "group_id";
$filtervals = array();
$distinct = "";
$sortby = "";
$sortexpr = "";
$limit = 20;
$ranker = SPH_RANK_PROXIMITY_BM25;
$select = "";
for($i=0; $i<count($args); $i++)
{
	$arg = $args[$i];

	if ($arg=="-h" || $arg=="--host")				$host = $args[++$i];
	elseif ($arg=="-p" || $arg=="--port")		$port = (int)$args[++$i];
	elseif ($arg=="-i" || $arg=="--index")		$index = $args[++$i];
	elseif ($arg=="-s" || $arg=="--sortby")		{ $sortby = $args[++$i]; $sortexpr = ""; }
	elseif ($arg=="-S" || $arg=="--sortexpr")	{ $sortexpr = $args[++$i]; $sortby = ""; }
	elseif ($arg=="-a" || $arg=="--any")			$mode = SPH_MATCH_ANY;
	elseif ($arg=="-b" || $arg=="--boolean")		$mode = SPH_MATCH_BOOLEAN;
	elseif ($arg=="-e" || $arg=="--extended")	$mode = SPH_MATCH_EXTENDED;
	elseif ($arg=="-e2")							$mode = SPH_MATCH_EXTENDED2;
	elseif ($arg=="-ph"|| $arg=="--phrase")		$mode = SPH_MATCH_PHRASE;
	elseif ($arg=="-f" || $arg=="--filter")		$filter = $args[++$i];
	elseif ($arg=="-v" || $arg=="--value")		$filtervals[] = $args[++$i];
	elseif ($arg=="-g" || $arg=="--groupby")		$groupby = $args[++$i];
	elseif ($arg=="-gs"|| $arg=="--groupsort")	$groupsort = $args[++$i];
	elseif ($arg=="-d" || $arg=="--distinct")	$distinct = $args[++$i];
	elseif ($arg=="-l" || $arg=="--limit")		$limit = (int)$args[++$i];
	elseif ($arg=="--select")					$select = $args[++$i];
	elseif ($arg=="-fr"|| $arg=="--filterrange")	$cl->setFilterRange($args[++$i], $args[++$i], $args[++$i]);
	elseif ($arg=="-r")
	{
		$arg = strtolower($args[++$i]);
		if ($arg=="bm25")		$ranker = SPH_RANK_BM25;
		if ($arg=="none")		$ranker = SPH_RANK_NONE;
		if ($arg=="wordcount")$ranker = SPH_RANK_WORDCOUNT;
		if ($arg=="fieldmask")$ranker = SPH_RANK_FIELDMASK;
		if ($arg=="sph04")	$ranker = SPH_RANK_SPH04;
	}
	else
		$q .= $args[$i] . " ";
}

////////////
// do query
////////////

$cl->setServer($host, $port);
$cl->setConnectTimeout(1);
$cl->setArrayResult(true);
$cl->setMatchMode($mode);
if (count($filtervals))	$cl->setFilter($filter, $filtervals);
if ($groupby)				$cl->setGroupBy($groupby, SPH_GROUPBY_ATTR, $groupsort);
if ($sortby)				$cl->setSortMode(SPH_SORT_EXTENDED, $sortby);
if ($sortexpr)			$cl->setSortMode(SPH_SORT_EXPR, $sortexpr);
if ($distinct)			$cl->setGroupDistinct($distinct);
if ($select)				$cl->setSelect($select);
if ($limit)				$cl->setLimits(0, $limit,($limit>1000) ? $limit : 1000);
$cl->setRankingMode($ranker);
$res = $cl->query($q, $index);

////////////////
// print me out
////////////////

if ($res===false)
{
	print "Query failed: " . $cl->getLastError() . ".\n";

} else
{
	if ($cl->getLastWarning())
		print "WARNING: " . $cl->getLastWarning() . "\n\n";

	print "Query '$q' retrieved $res[total] of $res[total_found] matches in $res[time] sec.\n";
	print "Query stats:\n";
	if (is_array($res["words"]))
		foreach($res["words"] as $word => $info)
			print "    '$word' found $info[hits] times in $info[docs] documents\n";
	print "\n";

	if (is_array($res["matches"]))
	{
		$n = 1;
		print "Matches:\n";
		foreach($res["matches"] as $docinfo)
		{
			print "$n. doc_id=$docinfo[id], weight=$docinfo[weight]";
			foreach($res["attrs"] as $attrname => $attrtype)
			{
				$value = $docinfo["attrs"][$attrname];
				if ($attrtype==SPH_ATTR_MULTI || $attrtype==SPH_ATTR_MULTI64)
				{
					$value = "(" . join(",", $value) .")";
				} else
				{
					if ($attrtype==SPH_ATTR_TIMESTAMP)
						$value = date("Y-m-d H:i:s", $value);
				}
				print ", $attrname=$value";
			}
			print "\n";
			$n++;
		}
	}
}
