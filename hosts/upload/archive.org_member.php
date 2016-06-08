<?php
######## Account Info ########s
$upload_acc['archive.org']['user'] = ''; //Set your login
$upload_acc['archive.org']['pass'] = ''; //Set your password
########################

$_GET['proxy'] = isset($_GET['proxy']) ? $_GET['proxy'] : '';

$not_done = true;

if ($upload_acc['archive.org']['user'] && $upload_acc['archive.org']['pass'])
{
    $default_acc = true;

    $_REQUEST['up_login'] = $upload_acc['archive.org']['user'];
    $_REQUEST['up_pass'] = $upload_acc['archive.org']['pass'];
    $_REQUEST['action'] = 'FORM';

    echo "<b><center>Using Default Login.</center></b>\n";
}
else
    $default_acc = false;

if (empty($_REQUEST['action']) || $_REQUEST['action'] != 'FORM')
{
    echo "<table border='0' style='width:270px;' cellspacing='0' align='center'>
	<form method='POST'>
	<input type='hidden' name='action' value='FORM' />
	<tr><td style='white-space:nowrap;'>Email address*</td><td>&nbsp;<input type='text' name='up_login' value='' required='required' style='width:160px;' /></td></tr>
	<tr><td style='white-space:nowrap;'>Password*</td><td>&nbsp;<input type='password' name='up_pass' value='' required='required' style='width:160px;' /></td></tr>\n";
    echo "<tr><td colspan='2' align='center'><br />Upload options *<br /><br /></td></tr>
	<tr><td style='white-space:nowrap;'>Identifier (Bucket):</td><td>&nbsp;<input type='text' name='bucket' pattern='[0-9a-zA-Z_\.\-]{3,255}' title='Please use only unaccented letters, numbers, dashes, underscores or periods.' style='width:160px;' /></td></tr>
	<tr><td style='white-space:nowrap;'>Media Type:</td><td>&nbsp;<select name='up_mediatype' style='width:160px;'>\n";
    foreach($media_types as $type) echo "\t<option value='$type'>".ucfirst($type)."</option>\n";
    echo "</select></td></tr>\n";
    echo "<tr><td style='white-space:nowrap;'>This is a test item?</td><td>&nbsp;<input type='checkbox' name='is_test_item' value='yes' /> <i>(remove after 30 days)</i></td></tr>";
    echo "<tr><td colspan='2' align='center'><br /><input type='submit' value='Upload' /></td></tr>\n";
    echo "<tr><td colspan='2' align='center'><i>*You can set it as default in <b>".basename(__FILE__)."</b></i></td></tr>\n";
    echo "</table>\n</form>\n";
}
else
{
    $login = $not_done = false;
    $domain = 'archive.org';
    $referer = "https://$domain/";

    // Login
    echo "<table style='width:600px;margin:auto;'>\n<tr><td align='center'>\n<div id='login' width='100%' align='center'>Login to $domain</div>\n";

    $cookie = array();

    if (!empty($_REQUEST['up_login']) && !empty($_REQUEST['up_pass']))
    {
        if (!empty($_REQUEST['A_encrypted']))
        {
            $_REQUEST['up_login'] = decrypt(urldecode($_REQUEST['up_login']));
            $_REQUEST['up_pass'] = decrypt(urldecode($_REQUEST['up_pass']));

            unset($_REQUEST['A_encrypted']);
        }

        $page = geturl($domain, 80, '/account/login.php', $referer.'account/login.php', $cookie, 0, 0, $_GET['proxy'], $pauth);

        is_page($page);

        $cookie = GetCookiesArr($page, $cookie);

        $post = array();
        $post['username'] = urlencode($_REQUEST['up_login']);
        $post['password'] = urlencode($_REQUEST['up_pass']);
        $post['action'] = 'login';
        $post['referer'] = urlencode($referer . 'create');;
        $post['submit'] = 'Log+in';

        $page = geturl($domain, 80, '/account/login.php', $referer.'account/login.php', $cookie, $post, 0, $_GET['proxy'], $pauth);

        is_page($page);

        $cookie = GetCookiesArr($page, $cookie);

        if (!preg_match('@logged-in-user@i', $page, $uname))
        {
            is_present($page, 'That password seems incorrect', 'Your login password is incorrect');
            is_present($page, 'The email you entered isn\'t in the Internet Archive system', 'Your email address is not in the Internet Archive system');
        }

        $login = true;
    }
    else
    {
        html_error('Login failed: User/Password empty.');
    }

    echo "<script type='text/javascript'>document.getElementById('login').style.display='none';</script>\n";

    // Gathering API Code
    echo "<table style='width:600px;margin:auto;'>\n<tr><td align='center'>\n<div id='gathapi' width='100%' align='center'>Getting API Access</div>\n";

    $s3 = geturl($domain, 80, '/account/s3.php', $referer.'account/login.php', $cookie, 0, 0, $_GET['proxy'], $pauth);

    is_page($s3);

    $cookie = GetCookiesArr($s3, $cookie);

    if (preg_match('@To get your S3 credentials check the box@', $s3))
    {
        $post = array();
        $post['confirm'] = 'on';
        $post['generateNewKeys'] = urlencode('Generate New Keys');

        $s3 = geturl($domain, 80, '/account/s3.php', $referer.'/account/s3.php', $cookie, $post, 0, $_GET['proxy'], $pauth);
    }

    if (!preg_match_all('@(Your S3 (?:access|secret) key\: [a-zA-Z0-9]{16})@', $s3, $s3Match))
    {
        html_error("We can't find a valid access and secret keys");
    }

    list($accessKey, $securityKey) = getS3ApiInfo($s3Match[0]);

    $_REQUEST['up_login'] = $accessKey;
    $_REQUEST['up_pass'] = $securityKey;

    // Pre-preparing to upload
    echo "<script type='text/javascript'>document.getElementById('gathapi').style.display='none';</script>\n";

    // Clean and Validate Filename
    $lname = preg_replace('@^\.|\.\.|\.$|[^0-9a-zA-Z_\.\-]@', '', str_replace(' ', '_', $lname));

    if (empty($lname))
    {
        html_error('Filename not allowed: "Please use only unaccented letters, numbers, dashes, underscores or periods".');
    }

    // Validate bucket name.
    if (isset($_REQUEST['bucket']))
    {
        $_REQUEST['bucket'] = trim($_REQUEST['bucket']);
    }

    if (!empty($_REQUEST['bucket']))
    {
        $len = strlen($_REQUEST['bucket']);

        if ($len < 3 || $len > 255 || preg_match('@^\.|\.\.|\.$|[^0-9a-zA-Z_\.\-]@', $_REQUEST['bucket']))
        {
            html_error('Error: Invalid Bucket Name.');
        }
    }
    else
    {
        $_REQUEST['bucket'] = pathinfo($lname, PATHINFO_FILENAME);

        if (strlen($_REQUEST['bucket']) < 3)
        {
            html_error('Error: Empty Bucket Name.');
        }
    }

    $cookie = array();

    if (!empty($_REQUEST['up_login']) && !empty($_REQUEST['up_pass']))
    {
        if (!empty($_REQUEST['A_encrypted']))
        {
            $_REQUEST['up_login'] = decrypt(urldecode($_REQUEST['up_login']));
            $_REQUEST['up_pass'] = decrypt(urldecode($_REQUEST['up_pass']));

            unset($_REQUEST['A_encrypted']);
        }

        $iaS3AccessKey = trim($_REQUEST['up_login']);
        $iaS3SecretKey = trim($_REQUEST['up_pass']);

        if (preg_match('@[^A-Za-z\d+/=]@', $iaS3AccessKey.$iaS3SecretKey))
        {
            // Simple check for invalid chars at API keys.
            html_error('Error: Invalid Character Found at API Key.');
        }
    }
    else
    {
        html_error('Error: Empty Access Key or Secret Key.');
    }

    // Preparing Upload
    echo "<script type='text/javascript'>document.getElementById('login').style.display='none';</script>\n<div id='info' width='100%' align='center'>Preparing Upload</div>\n";

    $uploadHeaders = array();

    // Test Bucket
    $page = ias3Request($_REQUEST['bucket']);
    $status = intval(substr($page, 9, 3));

    if ($status == 200)
    {
        if (!empty($_REQUEST['is_test_item']))
        {
            html_error('Bucket must not exist for upload a test item.');
        }

        $bucketExists = true;
        $bucket = page2xmldom($page, 'Error Loading Bucket');

        if (ias3FileExists($bucket, $lname))
        {
            html_error('Error: A file already exists with the same name.');
        }
    }
    else if ($status == 404)
    {
        $bucketExists = $bucket = false;
        $uploadHeaders['x-archive-auto-make-bucket'] = '1';
        $uploadHeaders['x-archive-meta-submitter'] = 'Rapidleech';

        if (!empty($_REQUEST['is_test_item']))
        {
            $uploadHeaders['x-archive-meta-description'] = $uploadHeaders['x-archive-meta-subject'] = 'test item';
            $uploadHeaders['x-archive-meta-collection'] = 'test_collection';
        }
        else
        {
            $uploadHeaders['x-archive-meta-description'] = 'Uploaded with Rapidleech';
            $uploadHeaders['x-archive-meta-subject'] = 'Rapidleech';
        }
        if (!empty($_REQUEST['up_mediatype']) && in_array($_REQUEST['up_mediatype'], $media_types))
        {
            $uploadHeaders['x-archive-meta-mediatype'] = $_REQUEST['up_mediatype'];
        }
    }
    else
    {
        html_error('[Error Pre Checking Bucket]: HTTP ' . $status);
    }

    // Pre-Upload test
    $page = ul_GetPage(($GLOBALS['use_https'] ? 'https' : 'http').'://s3.us.archive.org/?check_limit=1&accesskey='.urlencode($iaS3AccessKey).'&bucket='.urlencode($_REQUEST['bucket']));
    $json = json2array($page, 'Pre-Upload Error');

    if ($json['over_limit'] != 0)
    {
        textarea($json);
        html_error('The server is overloaded, please try to upload later.');
    }

    if (!empty($uploadHeaders))
    {
        $uploadHeaders = array_map('iaS3HeaderEncode', $uploadHeaders);
    }

    $uploadHeaders['Authorization'] = 'LOW ' . $iaS3AccessKey . ':' . $iaS3SecretKey;
    $uploadReferer = $referer;

    foreach ($uploadHeaders as $tok => $val)
    {
        $uploadReferer .= "\r\n$tok: $val";
    }

    // Uploading
    echo "<script type='text/javascript'>document.getElementById('info').style.display='none';</script>\n";

    $url = parse_url('http://s3.us.archive.org/' . urlencode($_REQUEST['bucket']) . '/' . urlencode($lname));
    $upfiles = putfile($url['host'], defport($url), $url['path'].(!empty($url['query']) ? '?'.$url['query'] : ''), $uploadReferer, $cookie, $lfile, $lname, $_GET['proxy'], $pauth, 0, $url['scheme']);

    // Upload Finished
    echo "<script type='text/javascript'>document.getElementById('progressblock').style.display='none';</script>\n";

    is_page($upfiles);

    if (intval(substr($upfiles, 9, 3)) != 200)
    {
        if (preg_match('@<Error><Code>(\w+)</Code><Message>(?>(.*?)</Message>)@i', $upfiles, $err))
        {
            switch ($err[1])
            {
                case 'AccessDenied':
                    html_error('Upload Error: Access to bucket denied, make sure that this bucket is yours.');
                case 'InvalidAccessKeyId':
                    html_error('Upload Error: Invalid AccessKey.');
                case 'SignatureDoesNotMatch':
                    html_error('Upload Error: Invalid or Incorrect SecretKey.');
                case 'SlowDown': case 'ServiceUnavailable':
                    html_error('The server is overloaded and discarted this upload, please try to upload later.');
                default:
                    html_error("Upload Error [{$err[1]}]: " . htmlspecialchars($err[2]));
            }
        }
        textarea($upfiles);
        html_error('Unknown Upload Error');
    }

    $download_link = $referer . 'download/' . urlencode($_REQUEST['bucket']) . '/' . urlencode($lname);
    $stat_link = $referer . 'catalog.php?history=1&identifier=' . urlencode($_REQUEST['bucket']);
}

function getS3ApiInfo($s3Match)
{
    $keys = array();

    if (!empty($s3Match))
    {
        for ($i = 0; $i <= sizeof($s3Match); $i++)
        {
            if (preg_match('@access@', $s3Match[$i]))
            {
                $accessKey = trim(str_replace('Your S3 access key:', '', $s3Match[$i]));

                if (strlen($accessKey) !== 16)
                {
                    return false;
                }

                $keys[] = $accessKey;
            }
            elseif (preg_match('@secret@', $s3Match[$i]))
            {
                $secretKey = trim(str_replace('Your S3 secret key:', '', $s3Match[$i]));

                if (strlen($secretKey) !== 16)
                {
                    return false;
                }

                $keys[] = $secretKey;
            }
        }
        return $keys;
    }
    return array();
}

function ias3FileExists($bucket, $filename)
{
    $files = $bucket->getElementsByTagName('Contents');

    if ($files->length > 0 && !empty($filename))
    {
        $filename = strtolower($filename);

        foreach ($files as $file)
        {
            if (strtolower($file->getElementsByTagName('Key')->item(0)->nodeValue) == $filename)
            {
                return $file;
            }
        }
    }

    return false;
}

function ias3Request($path = '', $header = array())
{
    if (!is_array($header))
    {
        $header = array();
    }

    if (!empty($header))
    {
        $header = array_map('iaS3HeaderEncode', $header);
    }

    $header['Authorization'] = 'LOW ' . $GLOBALS['iaS3AccessKey'] . ':' . $GLOBALS['iaS3SecretKey'];

    $headers = '';

    foreach ($header as $tok => $val)
    {
        $headers .= "\r\n$tok: $val";
    }

    $count = 0;
    $host = 's3.us.archive.org';

    do
    {
        $page = ul_GetPage(($GLOBALS['use_https'] ? 'https' : 'http')."://$host/$path", 0, 0, $GLOBALS['referer'].$headers);
        $status = intval(substr($page, 9, 3));

        if ($status == 307)
        {
            if ($count >= 2)
            {
                html_error('Redirect Loop Detected.');
            }

            if (!preg_match('@(?:\nLocation: https?://|<Endpoint>)((?:[\w\-]+\.)*archive.org)@i', $page, $host))
            {
                html_error('Redirect endpoint not found.');
            }

            $host = $host[1];
        }
    }
    while ($count++ < 2 && $status == 307);

    return $page;
}

function iaS3HeaderEncode($v)
{
    return (preg_match('/[^\x20-\x7E]/', $v) ? 'uri('.rawurlencode($v).')' : $v);
}

function page2xmldom($page, $errorPrefix = 'Error')
{
    if (!class_exists('DOMDocument'))
    {
        html_error('Error: Please install/enable the DOM module in php.');
    }

    // Remove Headers
    if (($pos = strpos($page, "\r\n\r\n")) > 0)
    {
        $body = trim(substr($page, $pos + 4));
    }

    $dom = new DOMDocument();

    if (!$dom->loadXML($body))
    {
        html_error("[$errorPrefix]: Error reading XML.");
    }

    return $dom;
}

function json2array($content, $errorPrefix = 'Error')
{
    if (!function_exists('json_decode'))
    {
        html_error('Error: Please enable JSON in php.');
    }

    if (empty($content))
    {
        return NULL;
    }

    $content = ltrim($content);

    if (($pos = strpos($content, "\r\n\r\n")) > 0)
    {
        $content = trim(substr($content, $pos + 4));
    }

    $cb_pos = strpos($content, '{');
    $sb_pos = strpos($content, '[');

    if ($cb_pos === false && $sb_pos === false)
    {
        html_error("[$errorPrefix]: JSON start braces not found.");
    }

    $sb = ($cb_pos === false || $sb_pos < $cb_pos) ? true : false;
    $content = substr($content, strpos($content, ($sb ? '[' : '{')));$content = substr($content, 0, strrpos($content, ($sb ? ']' : '}')) + 1);

    if (empty($content))
    {
        html_error("[$errorPrefix]: No JSON content.");
    }

    $rply = json_decode($content, true);

    if ($rply === NULL)
    {
        html_error("[$errorPrefix]: Error reading JSON.");
    }

    return $rply;
}

function ul_GetPage($link, $cookie = 0, $post = 0, $referer = 0, $auth = 0, $XMLRequest = 0)
{
    if (!$referer && !empty($GLOBALS['Referer']))
    {
        $referer = $GLOBALS['Referer'];
    }

    if ($GLOBALS['use_curl'])
    {
        if ($XMLRequest)
        {
            $referer .= "\r\nX-Requested-With: XMLHttpRequest";
        }

        $page = cURL($link, $cookie, $post, $referer, $auth);
    }
    else
    {
        global $pauth;

        $Url = parse_url($link);
        $page = geturl($Url['host'], defport($Url), $Url['path'] . (!empty($Url['query']) ? '?' . $Url['query'] : ''), $referer, $cookie, $post, 0, !empty($_GET['proxy']) ? $_GET['proxy'] : '', $pauth, $auth, $Url['scheme'], 0, $XMLRequest);
        is_page($page);
    }

    return $page;
}

//[08-6-2016]  Rewritten by Codetimeup & thx to Th3-822.

?>