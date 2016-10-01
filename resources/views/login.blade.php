<!DOCTYPE html>
<html>
<head>
	<title>idOS Authentication</title>
	<style media="screen">
		body,html {
			background-color: #f1f1f1;
			font-family: 'Helvetica Neue', Helvetica, sans-serif;
			color: #555;
		}
		.ct {
			position: absolute;
			display: table;
			height: 100%;
			width: 100%;
		}
		p {
			text-align: center;
			vertical-align: middle;
			display: table-cell;
		}
		span {
			position: relative;
			top: -100px;
			font-size: 1.3em;
		}
	</style>
</head>
<body>
	<div class="ct">
		<p>
			<span>Closing window..</span>
		</p>
	</div>

	<script type="text/javascript">
		if (window.opener && typeof (window.opener.postMessage) == 'function') {
			window.opener.postMessage({
				message: "idos:source.added",
				tokens: {@foreach ($tokens as $key => $token)
					@if ($loop->last)
						"{{$key}}": "{{$token}}"
				    @else 
						"{{$key}}": "{{$token}}",
				    @endif
					@endforeach
				},
				source: "{{ $source }}"
			}, "*");
		}
		window.close();

	</script>
</body>
</html>
