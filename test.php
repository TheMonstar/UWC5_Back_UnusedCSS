<html>
<head>
    <style>
        body {
            margin-bottom: 10px;
            padding-left: 10px;
        }
        body {
            margin: 0;
            padding: 0;
        }
        .test {
            list-style  : none;
        }
        .mast {
        }
        .one {
            font-size: 120%;
        }
        .one ~ .three+ .four .five+.six {
            font-size: 150%;
        }
        .three + .four .five {
            font-weight: bold;
        }
        .four * {
            text-decoration: underline;
        }
        .five * {
            text-decoration: underline;
        }
        span[class*=hr] {
            text-decoration: underline;
        }
        [type=test] {
            font-size: 150%;
        }
    </style>
</head>
<body>
<span class="one">abcd</span>
<span class="two">abcd</span>
<span class="three">abcd</span>
<span class="four">4abcd<span class="five">5abcd</span><span class="six">6abcd</span></span>
<span class="five">abcd</span>
<div  type="test">refdfsdf</div>
</body>
</html>