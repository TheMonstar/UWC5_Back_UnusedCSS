<?php
/**
 * Created by JetBrains PhpStorm.
 * User: zeus
 * Date: 10/25/13
 * Time: 7:54 PM
 */
?>
<style>
    .one + .two {
        font-size: 150%;
    }
    .three ~ .four {
        font-weight: bold;
    }
    .two ~ .five {
        text-decoration: underline;
    }
    .four > .five {
        text-decoration: underline;
    }
</style>
<form name="form" action="/web.php">
    url: <input name="url"/>
    level: <select name="level">
        <option>0</option>
        <option>1</option>
        <option>2</option>
        <option>3</option>
    </select>
    limit: <input name="limit"/>
    <button>Send</button>
</form>
<div id="container"></div>
<script type="text/javascript" src="/jquery-1.7.2.min.js"></script>
<script type="text/javascript">
    $(function(){
        $('form').submit(function(e){
            e.preventDefault();
            $('#container').html('Query processing');
            $.ajax({
                url: this.action,
                data: $(this).serialize(),
                success: function(data) {
                    $('#container').html(data);
                }
            });
        })
        $('#container').on('click' , 'div',function(){
            $(this).next().toggle();
        })
    });
</script>