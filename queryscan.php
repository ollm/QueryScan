<?php

/* QueryScan v0.1 */

// Database
$dbHost = 'localhost';
$dbPort = '';
$dbUser = '';
$dbPass = '';

// Config
$loadFrom = './queryscan.json.gz'; // location of the file to load, it is only necessary if you want to see the data that was previously saved in a file.
$saveIn = './queryscan.json.gz'; // Location of the file to save, it is only necessary if you want to save the result or you are executing the script from the command line.
$scanDuration = 60; // Scan duration in seconds
$scanInterval = 25; // Time between query scans in milliseconds
$onePixelEquals = 500; // Equivalence in milliseconds of a pixel
$highlightQueriesLonger = 100; // Highlights queries that last longer than the specified milliseconds
$trimQueries = 1000; // Queries will be cut if they exceed this number of characters
$cliOnly = true; // It will only be possible to execute the script from CLI, from the browser you can only see the results loaded from the form.

// Connect to the database
$mysql = mysqli_connect($dbHost.($dbPort ? ':'.$dbPort : ''), $dbUser, $dbPass);
mysqli_set_charset($mysql, 'utf8mb4');

// Increase the execution time
ini_set('max_execution_time', $scanDuration + 60);

$cli = (php_sapi_name() == 'cli') ? true : false;

if(!$cli)
{

	?><!DOCTYPE html>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<meta charset="utf-8" /> 
	<style type="text/css">
		
		*
		{
			font-family: Arial;
			font-size: 13px;
		}

		.green
		{
			color: green;
		}

		.red
		{
			color: red;
		}

		.button
		{
			cursor: pointer;
			color: blue;
		}

		.button:hover
		{
			text-decoration: underline;
		}


	</style>

	With this script you can record the queries made during a period of time, graphically showing the slow queries.
	<br><br>	
	Scan Duration: <b><?php echo $scanDuration; ?>s</b>, Scan Interval: <b><?php echo $scanInterval; ?>ms</b>, One Pixel Equals: <b><?php echo $onePixelEquals; ?>ms</b>, Highlight Queries Longer: <b><?php echo $highlightQueriesLonger; ?>ms</b>
	<br>
	Consultations that last less than <b><?php echo $scanInterval; ?>ms</b> have the possibility of not registering, queryDuration / scanInterval * 100 = Percentage of possibilities to register
	<br><br>

	<?php

	if($mysql)
	{
		?> <span class="green">Successful connection to the database</span> <?php
	}
	else
	{
		?> <span class="red">Unable to connect to the database</span> <?php
	}

	?>

	<br><br>
	<?php
		if($cliOnly) {
	?>
		<span style="color: red;">The "CGI only" mode is activated, you can not run a scan or see the last scan, you can only see the scans that you upload from the form, you can disable "asdasd" mode by editing this file or you can use CLI instead (Command line)</span>
		<br><br>
	<?php
		}
	?>
	<form method="POST" style="display: none;" id="start">
		<input type="hidden" name="start" value="1" />
	</form>
	<a href="javascript:void(0)" onclick="$('#start').submit()"><span class="button">Start scan</span></a>
	<br><br>
	Or
	<br><br>
	<form enctype="multipart/form-data" method="POST">
		Upload previous scan: <input name="file" type="file" />
		<input type="submit" value="Send" />
	</form>

	<?php

}
else
{
	if($mysql)
		echo 'Successful connection to the database'.PHP_EOL;
	else
		exit('Error: Unable to connect to the database'.PHP_EOL);
}

$queries = array();
$load = array();

$l = 0;

$scanIntervalSeconds = $scanInterval / 1000;
$numberLoops = $scanDuration * (1000 / $scanInterval);
$sleepDuration = $scanInterval * 1000;
$onePixelEqualsSeconds = $onePixelEquals / 1000;
$highlightQueriesLongerSeconds = $highlightQueriesLonger / 1000;

if((isset($_POST['start']) || $cli) && $mysql && (!$cliOnly || $cli))
{
	if(!empty($saveIn))
		echo 'The data is saved in the file "'.$saveIn.'"'.PHP_EOL;
	else
		exit('Error: No file to save the data has been established.'.PHP_EOL);

	if($cli)
		echo 'The scan has started'.PHP_EOL.PHP_EOL;

	$cliPercents = array();

	$loadLastCheck = 0;

	$startTime = microtime(true);

	$previousQueries = array();

	for($i = 0; $i < $numberLoops; $i++)
	{
		$loopTime = microtime(true);

		$currentQueries = array();

		$result = mysqli_query($mysql, 'SHOW FULL PROCESSLIST');
		while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))
		{
			$queryId = $row['Id'];

			if(!empty($row['Info']) && $row['Info'] != 'SHOW FULL PROCESSLIST')
			{
				$md5Query = md5($row['Info']);

				$currentQueries[$md5Query] = true;

				$query = mb_substr($row['Info'], 0, $trimQueries, 'utf-8');

				if(strlen($row['Info']) > $trimQueries)
					$query .= '... Cropped query';

				if(isset($queries[$queryId]['queries'][$md5Query]))
				{
					$row = $queries[$queryId];
					$row['end'] = $loopTime;
					$row['duration'] = $loopTime - $row['start'];
					$row['realDuration'] = $scanIntervalSeconds + $row['realDuration'];

					$rowQueries = $row['queries'][$md5Query];

					if(isset($previousQueries[$md5Query]))
					{
						$row['queries'][$md5Query]['executions'][$rowQueries['index']]['end'] = $loopTime;
					}
					else
					{
						$row['queries'][$md5Query]['executions'][] = array(
							'start'	=>	$loopTime,
							'end'	=>	$loopTime,
						);
						$row['queries'][$md5Query]['index']++;
					}

					$queries[$queryId] = $row;
				}
				else if(isset($queries[$queryId]))
				{
					$row = $queries[$queryId];
					$row['end'] = $loopTime;
					$row['duration'] = $loopTime - $row['start'];
					$row['realDuration'] = $scanIntervalSeconds + $row['realDuration'];

					$row['queries'][$md5Query] = array(
						'query'	=>	$query,
						'index'	=>	0,
						'executions'	=>	array(
							array(
								'start'	=>	$loopTime,
								'end'	=>	$loopTime,
							)
						),
					);

					$queries[$queryId] = $row;
				}
				else
				{
					$row = array(
						'duration'	=>	$scanIntervalSeconds,
						'realDuration'	=>	$scanIntervalSeconds,
						'start'	=>	$loopTime,
						'end'	=>	$loopTime,
						'queries'	=>	array(
							$md5Query	=>	array(
								'query'	=>	$query,
								'index'	=>	0,
								'executions'	=>	array(
									array(
										'start'	=>	$loopTime,
										'end'	=>	$loopTime,
									)
								)
							)
						)
					);

					$queries[$queryId] = $row;
				}
			}
		}
		mysqli_free_result($result);

		$l++;

		// Activate in cloudflare or other CDNs to avoid a 504 error
		if(!$cli)
			echo '                      ';

		// Check server load every $onePixelEquals ms
		if($loopTime - $loadLastCheck > $onePixelEqualsSeconds)
		{
			$load[] =array(
				'time'	=>	$loopTime,
				'load'	=>	sys_getloadavg()[0],
			);
			$l = 0;
			ob_flush();
			$loadLastCheck = $loopTime;
		}

		$previousQueries = $currentQueries;

		usleep($sleepDuration);

		if($cli)
		{
			$percent = round($i / $numberLoops * 100);

			if($percent < 10)
				$percent = '0'.$percent;

			if(!isset($cliPercents[$percent]))
			{
				$seconds = round((($numberLoops - $i) * $scanInterval) / 1000);

				$hours = floor($seconds / 3600);
				$minutes = floor($seconds / 60) - ($hours * 60);
				$seconds = $seconds - ($hours * 60 * 60) - ($minutes * 60);

				echo $percent.'% - '.($hours ? $hours.'h ' : '').($minutes ? $minutes.'m ' : '').$seconds.'s left'.PHP_EOL;

				$cliPercents[$percent] = true;
			}
		}
	}

	if($cli)
		echo PHP_EOL.'Scan is finished'.PHP_EOL;

	$endTime = microtime(true);

	// Save the data in the file
	if(!empty($saveIn))
	{
		$gzip = gzencode(json_encode(array(
			'queries'	=>	$queries,
			'load'	=>	$load,
			'startTime'	=>	$startTime,
			'endTime'	=>	$endTime,
		)), 6);
		file_put_contents($saveIn, $gzip);

		// Change file permissions
		chmod($saveIn, 0640);
	}
}
else if((!empty($loadFrom) && file_exists($loadFrom) && !$cliOnly) || (isset($_FILES['file']['tmp_name']) && file_exists($_FILES['file']['tmp_name'])))
{
	if(isset($_FILES['file']['tmp_name']) && file_exists($_FILES['file']['tmp_name']))
		$file = $_FILES['file']['tmp_name'];
	else
		$file = $loadFrom;

	$json = gzdecode(file_get_contents($file));
	$data = json_decode($json, true);

	$queries = $data['queries'];
	$load = $data['load'];
	$startTime = $data['startTime'];
	$endTime = $data['endTime'];
}

if(!empty($queries) && !$cli)
{

	?>

	<style type="text/css">
		
		.data
		{
			width: 100%;
			overflow-x: auto;
			box-sizing: border-box;
			padding-top: 10px;
		}

		.data > div
		{
			min-width: 100%;
			height: 100%;
		}

		.timeLine
		{
			height: 30px;
		}

		.timeLine > *
		{
			width: 1px;
			height: 20px;
			margin-top: 10px;
			background-color: rgba(0, 0, 0, 0.3);
			display: block;
			float: left;
			margin-left: <?php echo (1 / $onePixelEqualsSeconds - 1); ?>px;
		}

		.timeLine > div > span
		{
			width: 50px;
			margin-left: -25px;
			display: block;
			margin-top: -18px;
			text-align: center;
		}

		.timeLine > span
		{
			height: 10px;
			margin-top: 20px;
			background-color: rgba(0, 0, 0, 0.2);
		}

		.line
		{
			height: 100%;
			top: 0px;
			position: fixed;
			width: 1px;
			background-color: rgba(0, 0, 0, 0.3);
		}

		.load
		{
			padding-top: 10px;
			height: 100px;
			position: relative;
			z-index: 3;
		}

		.load > span
		{
			height: 20px;
		}

		.load > div
		{
			height: 80px;
		}

		.load > div > div
		{
			position: absolute;
			bottom: 0px;
			height: 80px;
			width: 1px;
		}

		.load > div > div > div
		{
			position: absolute;
			bottom: 0px;
			width: 1px;
			background-color: green;
		}

	</style>

	<script type="text/javascript">
		
		function dataSize()
		{
			$(window).width();

			$('.data').css({
				'height': ($(window).height() - 20)+'px',
			});

			$('.queries > div').css({
				'height': ($(window).height() - 200)+'px',
			});
		}

		$(window).on('resize', function(){

			dataSize();

		});

		$(window).on('mousemove', function(event){

			pageX = event.pageX;

			$('.line').css({
				'left': (pageX-1)+'px',
			});

		});
	</script>

	<style type="text/css">
		
		.popup
		{
			position: fixed;
			left: 10%;
			top: 10%;
			width: 80%;
			height: 80%;
			background: #fff;
			box-shadow: 0px 0px 10px black;
			z-index: 10;
			display: none;
		}

		.popup > div
		{
			margin: 10px;
			position: relative;
			overflow: auto;
			height: calc(100% - 50px);
		}

		.popup > div > div
		{
			float: left;
			width: 50%;
			height: 100%;
			overflow: auto;
			background: rgba(0, 0, 0, 0.1);
		}

		.popup > div > div > div:hover
		{
			background: rgba(0, 0, 0, 0.2);
		}

		.popup > div > pre
		{
			float: right;
			width: 50%;
			display: block;
			height: 100%;
			overflow: auto;
			margin: 0px;
		}

		.popup .query
		{
			border-bottom: 1px solid rgba(0, 0, 0, 0.5);
			height: 10px;
		}

		.popup .query > div
		{
			height: 10px !important;
			position: relative !important;
			float: left;
		}

	</style>

	<div class="popup">
		<span onclick="$('.popup').css('display', 'none')" style="float: right; margin-right: 10px;">Close</span>
		<span style="clear: both; display: block;"></span>
		<span>Pass over a row to see the query</span>
		<div>
			<pre></pre>
			<div></div>
		</div>
	</div>

	<div class="data">
		<script type="text/javascript">dataSize();</script>
		<div style="width: <?php echo ceil(($endTime - $startTime) / $onePixelEqualsSeconds); ?>px">
			<div class="timeLine">

				<?php  

				$loopNum = ceil($endTime - $startTime);

				$startTimeRound = round($startTime);

				$seconds = date('s', $startTimeRound);

				for($i = 0; $i < $loopNum; $i++)
				{
					if($seconds >= 60)
					{
						echo '<div><span>'.date('H:i', $startTimeRound + $secondsMore).'</span></div>';

						$seconds = 0;
					}
					else if($onePixelEquals <= 500)
					{
						echo '<span></span>';
					}

					$seconds++;
					$secondsMore++;
				}

				?>

			</div>
			<div class="line"></div>
			<div class="load">

				<span>Roll over to see the load: <span></span></span>
				<div>
					<?php

					$maxLoad = 0;

					foreach($load as $row)
					{
						if($row['load'] > $maxLoad)
							$maxLoad = $row['load'];
					}

					foreach($load as $row)
					{
						echo '<div style="left: '.(($row['time'] - $startTime) / $onePixelEqualsSeconds).'px" load="hour: '.date('H:i:s', $row['time']).', load: '.round($row['load'], 5).'"><div style="height: '.($row['load'] / $maxLoad * 80).'px;"></div></div>';
					}

					?>
				</div>
				<script type="text/javascript">
					
					$('.load > div > div').on('mouseenter', function(){

						$('.load > span > span').text($(this).attr('load'));

					});

				</script>
			</div>
			<div class="queries">
				<script type="text/javascript">dataSize();</script>
				<style type="text/css">

					.queries > div
					{
						padding-top: 10px;
						box-sizing: border-box;
						position: relative;
						padding-top: 10px;
						overflow-y: auto; 
					}

					.queries > span
					{
						height: 20px;
					}

					.queries > div > div:hover
					{
						background-color: rgba(0, 0, 0, 0.1);
					}

					.queries > div > div > div
					{
						height: 3px;
						position: relative; 
					}

					.execution
					{
						position: absolute;
						height: 3px; 
					}

				</style>
				<span>
					Each row is a connection to the database, press a row to see the queries, Pass over to see the duration: <span class="duration"></span>
					<br>
					Queries Not Highlighted: <span style="color: green;">green</span>/<span style="color: blue;">blue</span>, Highlighted Queries: <span style="color: red;">red</span>/<span style="color: orange;">orange</span>
				</span>
				<div>

					<?php

					foreach($queries as $row)
					{
						$con = '';

						$color = true;

						foreach($row['queries'] as $q)
						{
							$lines = '';

							$duration = 0;

							foreach($q['executions'] as $e)
							{
								$left = $e['start'] - $row['start'];

								$duration = $e['end'] - $e['start'];
								$width = $duration / $onePixelEqualsSeconds;

								$lines .= '<div class="execution" style="left: '.$left.'px; width: '.($width > 1 ? $width : 1).'px; background-color: '.(($highlightQueriesLongerSeconds > $duration) ? ($color ? 'green' : 'blue') : ($color ? 'red' : 'orange')).';" title="hour: '.date('H:i:s', $e['start']).', duration: '.round($duration, 3).'"></div>';
							}

							$con .= '<div class="query" query="'.htmlentities($q['query']).'">'.$lines.'</div>';

							$color = !$color;
						}

						echo '<div><div style="margin-left: '.(($row['start'] - $startTime) / $onePixelEqualsSeconds).'px;" data="hour: '.date('H:i:s', $row['start']).', connection duration: '.round($row['duration'], 3).', queries duration: '.round($row['realDuration'], 3).'">'.$con.'</div></div>';
					}

					?>

				</div>
				<script type="text/javascript">
					
					$('.queries > div > div').on('mouseenter', function(){

						$('.queries > span > span.duration').text($(this).children().attr('data'));

					});

					function popupMouseenter()
					{
						$('.popup .query').off('mouseenter').on('mouseenter', function(){

							titles = [];

							$(this).find('.execution').each(function(){

								titles.push($(this).attr('title'));

							});

							$('.popup pre').text(titles.join(', ')+"\n\n"+$(this).attr('query'));

						});
					}

					$('.queries > div > div').on('click', function(){

						$('.popup').css('display', 'block');
						$('.popup > div > div').html($(this).children().html());

						$('.popup > div > div > div').each(function(){

							$(this).css('margin-left', $(this).css('left'));

						});

						$('.popup pre').text('');

						popupMouseenter();

					});

					dataSize();

				</script>
			</div>
		</div>
	</div>
	<div>Used memory: <?php echo round(memory_get_peak_usage() / 1024 / 1024, 2); ?>MB</div>

	<?php

}

if($cli)
{
	echo 'Used memory: '.round(memory_get_peak_usage() / 1024 / 1024, 2).'MB'.PHP_EOL;
	echo 'Now you can check the result by opening queryscan.php from the browser'.PHP_EOL;
}

mysqli_close($mysql);

?>