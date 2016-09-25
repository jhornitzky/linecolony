<!DOCTYPE html>
<html>
    <head>
		<title>linecolony</title>
		<meta http-equiv="refresh" content="900">
		<link href="https://fonts.googleapis.com/css?family=Lato:100,300,500,700,900" rel="stylesheet" type="text/css">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">
		<link rel="stylesheet" href="css/main.css" rel="stylesheet" type="text/css">
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
    </head>
    <body>
        <div class="wrapper">
			<header class="container-fluid">
				<div class="row">
					<div class="col-md-6 page-title text-left">
						<h1><img src="images/logo.jpg" alt="linecolony"></h1>
					</div>
					<div class="col-md-6 updated text-right">
						<a href="/">Reload</a> | <span class="time">Updated {{ $time }}</span> | <a href="logout">Logout</a>
					</div>
				</div>
			</header>
            <div class="content">
				<div class="trees">
					@foreach ($trees as $tree)
					<div class="tree row">
						<div class="leaf title col-md-2">
							<p class="key">{{ $tree['titleKey'] }}</p>
							<p class="value">{{ $tree['titleValue'] }}</p>
						</div>
						<div class="col-md-12">
							<div class="row">
								@foreach ($tree['leaves'] as $leaf)
								<div class="leaf {{ $tree['css'] }} {{ $leaf['css'] }}">
									<p class="key" title="{{ $leaf['key'] }}">{{ $leaf['key'] }}</p>
									<p class="value" title="{{ $leaf['value'] }}">{{ $leaf['value'] }}</p>
								</div>
								@endforeach
							</div>
						</div>
					</div>
					@endforeach
				</div>
            </div>
        </div>
    </body>
</html>
