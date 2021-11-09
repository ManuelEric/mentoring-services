<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        .header {
            background: rgb(163, 238, 255);
            width: 100%;
            padding: 10px ;
        }
    </style>
</head>
<body>
<table style="width:100%;border-collapse:collapse;border:0;border-spacing:0;background:#ffffff;">
    <tr>
        <td align="center" style="padding:0;">
            <div class="header"><img src="{{ asset('img/new-logo-allin.png') }}" ></div>
        </td>
    </tr>
    <tr>
        <td>
            Hey {{ $name }}!
        </td>
    </tr>
    <tr>
        <td>
            Thank you for creating an account with us. Don't forget to complete your registration!
        </td>
    </tr>
    <tr>
        <td>
            Verification code : {{ $verification_code }}
        </td>
    </tr>
    <tr>
        <td>
            Thanks,<br>
            All-in Eduspace Team
        </td>
    </tr>
</table>

</body>
</html>