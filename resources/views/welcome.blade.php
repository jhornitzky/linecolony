<!DOCTYPE html>
<html>
    <head>
        <title>linecolony</title>

		<meta http-equiv="refresh" content="900">

        <link href="https://fonts.googleapis.com/css?family=Lato:100,300,500,700,900" rel="stylesheet" type="text/css">
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
		<!-- Optional theme -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">
		<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>

		<style>

		.color-primary-0 { color: #AA3939 }	/* Main Primary color */
.color-primary-1 { color: #FFAAAA }
.color-primary-2 { color: #D46A6A }
.color-primary-3 { color: #801515 }
.color-primary-4 { color: #550000 }

.color-secondary-1-0 { color: #AA6C39 }	/* Main Secondary color (1) */
.color-secondary-1-1 { color: #FFD1AA }
.color-secondary-1-2 { color: #D49A6A }
.color-secondary-1-3 { color: #804515 }
.color-secondary-1-4 { color: #552700 }

.color-secondary-2-0 { color: #226666 }	/* Main Secondary color (2) */
.color-secondary-2-1 { color: #669999 }
.color-secondary-2-2 { color: #407F7F }
.color-secondary-2-3 { color: #0D4D4D }
.color-secondary-2-4 { color: #003333 }

.color-complement-0 { color: #2D882D }	/* Main Complement color */
.color-complement-1 { color: #88CC88 }
.color-complement-2 { color: #55AA55 }
.color-complement-3 { color: #116611 }
.color-complement-4 { color: #004400 }

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

			.controls {
				padding-top: 1rem;
                font-size: 1rem;
				font-weight:500;
			}

			.tree {
				margin-top:1rem;
				margin-bottom:1rem;
				border-bottom:1px solid #333; /*#DDD*/
			}
            .leaf {
				padding-top: 1rem;
                font-size: 1rem;
				font-weight:500;
				min-height:104px;
            }
			.leaf.green {
				background-color:#55AA55;
				color:#FFF;
			}
			.leaf.amber {
				background-color:#D49A6A;
				color:#FFF;
			}
			.leaf.red {
				background-color:#D46A6A;
				color:#FFF;
			}
			.leaf.grey {
				background-color:#AAAAAA;
				color:#FFF;
			}
			.leaf.title .key {
				font-weight:700;
			}
			.leaf .key {
                margin-bottom:0;
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
							<p class="key">{{ $tree['titleKey'] }}</p>
							<p class="value">{{ $tree['titleValue'] }}</p>
						</div>
						@foreach ($tree['leaves'] as $leaf)
						<div class="leaf {{ $tree['css'] }} {{ $leaf['css'] }}">
							<p class="key">{{ $leaf['key'] }}</p>
							<p class="value">{{ $leaf['value'] }}</p>
						</div>
						@endforeach
						<div class="leaf outcome col-xs-1">
							<p class="key">{{ $tree['outcomeKey'] }}</p>
							<p class="value">{{ $tree['outcomeValue'] }}</p>
						</div>
					</div>
					@endforeach
				</div>
            </div>
        </div>
    </body>
</html>
