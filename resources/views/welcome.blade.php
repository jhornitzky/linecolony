<!DOCTYPE html>
<html>
    <head>
        <title>Laravel</title>

        <link href="https://fonts.googleapis.com/css?family=Lato:100,300,500,700,900" rel="stylesheet" type="text/css">
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
		<!-- Optional theme -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">
		<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
        <style>
            html, body {
                height: 100%;
				font-size: 14px;
            }

            body {
                margin: 0;
                padding: 0;
                width: 100%;
                display: table;
                font-weight: 100;
                font-family: 'Lato';
            }

            .container {
                text-align: center;
                display: table-cell;
                vertical-align: middle;
            }

            .content {
                text-align: center;
                display: block;
            }

            .page-title {
                font-size: 4rem;
            }

			.tree {
				margin-top:1rem;
				margin-bottom:1rem;
				border-bottom:1px solid #DDD;
			}
            .leaf {
                font-size: 1rem;
				font-weight:500;
            }
			.leaf .value {
                font-size: 3rem;
				font-weight:300;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="content">
                <div class="page-title">linecolony</div>
				<div class="controls">
					<span class="time">As at {{ $time }}</span><br>
					<a href="/">Reload</a>
				</div>
				<div class="trees container-fluid">
					@foreach ($trees as $tree)
					<div class="tree row">
						<div class="leaf title col-xs-1">
							<p class="key">{{ $tree['title'] }}</p>
							<p class="value"></p>
						</div>
						@foreach ($tree['leaves'] as $leaf)
						<div class="leaf col-xs-1">
							<p class="key">{{ $leaf['key'] }}</p>
							<p class="value">{{ $leaf['value'] }}</p>
						</div>
						@endforeach
					</div>
					@endforeach
				</div>
            </div>
        </div>
    </body>
</html>
