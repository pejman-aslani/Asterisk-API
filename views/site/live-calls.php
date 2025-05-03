<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تماس‌های فعال</title>
</head>
<body>

<h1>تماس‌های فعال</h1>

<div id="calls-list"></div>

<script>
    setInterval(function() {
        $.ajax({
            url: '/site/live-calls',
            method: 'GET',
            success: function(response) {
                console.log('Response received:', response);
                if (response && response.calls) {
                    var callsList = $('#calls-list');
                    callsList.empty();

                    response.calls.forEach(function(call) {
                        callsList.append('<p>' + call + '</p>');
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('خطا در دریافت داده‌ها');
            }
        });
    }, 5000);

</script>

</body>
</html>
