<?php

/**
 * 
 * APInsta v0.1
 * By AyoobAli.com
 * License: The MIT License (MIT)
 * Copyright (c) 2015 Ayoob Ali
 * 
 * This class was made as a workaround for Instagram Notification API.
 * IMPORTANT: This was not made to be a secure API as it uses the plain-text username/password for authentication, and it will save the user session ID in the directory 'savedsessions'.
 * 
 * Use:-
 * include('APInsta.class.php');
 * $insta = new \APInsta\Instagram();
 * $insta->login("Username", "Password");
 * $json = $insta->getNotifications();
 * 
 */

namespace APInsta;

class Instagram
{
    private $status = "Disconnected";
    private $showErrors = false;
    private $lastError = "";
    private $sessionID = "";
    private $username;

    private $curlURL = "https://instagram.com/accounts/login/ajax/";
    private $curlUserAgent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.124 Safari/537.36";
    private $curlHeader;
    private $curlPostData;
    private $curlReferer = "https://instagram.com/";
    private $curlToken;

    public function __construct()
    {
        $this->curlToken = md5( rand( 1, 5000 ) );
    }

    // Login & get Session ID
    public function login( $username, $password, $saveLogin = true )
    {
        if ( file_exists( $this->getSessionFile( $username ) ) ) {
            include ( $this->getSessionFile( $username ) );
            $savedSessionID;
            if ( strlen( trim( base64_decode( $savedSessionID ) ) ) > 150 ) {
                $this->setSessionID( base64_decode( $savedSessionID ) );
                $this->setStatus( "Connected" );
                $this->setUsername( $username );
                return true;
            }
        }

        if ( trim( $username ) == "" or trim( $password ) == "" ) {
            $this->ErrorMSG( "Username/Password can't be Empty." );
            return false;
        } else {

            $bodyPattern = '/\"authenticated\":true/';
            $headerPattern = '/sessionid=(.*?);/';

            $this->setURL( "https://instagram.com/accounts/login/ajax/" );
            $this->setReferer( "https://instagram.com/" );
            $this->setHeader( "X-CSRFToken: " . $this->getToken() );
            $this->setPostData( array( 'username' => $username, 'password' => $password ) );
            $response = $this->submit();

            if ( preg_match( $bodyPattern, $response['body'], $matches, PREG_OFFSET_CAPTURE ) ) {
                if ( preg_match( $headerPattern, $response['header'], $matches, PREG_OFFSET_CAPTURE ) ) {
                    $this->setSessionID( $matches[1][0] );
                    $this->setStatus( "Connected" );
                    $this->setUsername( $username );

                    if ( $saveLogin == true ) {
                        $data = '<?php $savedSessionID = "' . base64_encode( $this->getSessionID() ) . '"; ?>';
                        file_put_contents( $this->getSessionFile( $username ), $data );
                    }

                    return true;
                } else {
                    $this->ErrorMSG( "Can't get Session ID." );
                    return false;
                }
            } else {
                $this->ErrorMSG( "Wrong username or password." );
                return false;
            }
        }

    }

    // Save Session ID to a file
    public function saveLogin()
    {
        if ( $this->getStatus() == "Connected" ) {
            $data = '<?php $savedSessionID = "' . base64_encode( $this->getSessionID() ) . '"; ?>';
            file_put_contents( $this->getSessionFile(), $data );
        } else {
            $this->ErrorMSG( "You need to login first." );
            return false;
        }
    }

    // Get Regx data
    private function getRegx( $data, $pattern )
    {
        if ( preg_match( $pattern, $data, $matches, PREG_OFFSET_CAPTURE ) ) {
            return trim( $matches[1][0] );
        } else {
            return false;
        }
    }

    // Get user notifications
    public function getNotifications()
    {
        if ( $this->getStatus() == "Connected" ) {
            $this->setURL( "http://instagram.com/api/v1/news/inbox/" );
            $this->setReferer();
            $this->setHeader();
            $response = $this->submit();

            if ( $this->getRegx( $response['body'], '/\"status\":\"fail\"/' ) ) {
                $this->ErrorMSG( "Can't get data, try to logout then login." );
                return false;
            }

            $response['body'] = preg_replace( '/\s+/', ' ', $response['body'] );

            $pattern = '/<ul class=\"activity\">(.*?)<\/ul>/';
            preg_match_all( $pattern, $response['body'], $outData, PREG_PATTERN_ORDER );

            $i = 0;
            foreach ( $outData[1] as $key => $value ) {

                $pattern = '/<li (.*?)<\/li>/';
                preg_match_all( $pattern, $value, $outData2, PREG_PATTERN_ORDER );
                foreach ( $outData2[1] as $key2 => $value2 ) {

                    if ( $this->getRegx( $value2, '/<a href=\"instagram:\/\/user\?username=(.*?)\">/' ) ) {

                        $arr[$i]['Media']['ID'] = $this->getRegx( $value2, '/<a class=\"thumbnail gutter single-image\" href=\"instagram:\/\/media\?id=(.*?)\">/' );
                        $arr[$i]['Media']['Pic'] = $this->getRegx( $value2, '/<img src=\"(.*?)\"\/>/' );

                        $arr[$i]['Username'] = $this->getRegx( $value2, '/<a href=\"instagram:\/\/user\?username=(.*?)\">/' );

                        $tpdata = $this->getRegx( $value2, '/<a href=\"instagram:\/\/user\?username=(.*?)\">/' );

                        $tpdata = str_replace( "instagram://user?username=", "https://instagram.com/", $tpdata );
                        $tpdata = str_replace( "instagram://tag?name=", "https://instagram.com/explore/tags/", $tpdata );
                        $tpdata = str_replace( "</p>", "", $tpdata );
                        $tpdata = str_replace( "<p>", "", $tpdata );
                        $arr[$i]['Data'] = trim( $tpdata );

                        $arr[$i]['Time'] = $this->getRegx( $value2, '/<p class=\"timestamp\" data-timestamp=\"(.*?)\">/' );
                        date_default_timezone_set( 'Asia/Dubai' );
                        $arr[$i]['TimeUAE'] = date( "d/m/Y h:i:s A", $arr[$i]['Time'] );

                        $i++;
                    }

                }
            }

            //echo htmlentities( $response['body'] );
            return json_encode( $arr );
        } else {
            $this->ErrorMSG( "You are not logged in." );
            return false;
        }
    }

    // Logout
    public function logout()
    {
        if ( $this->getStatus() == "Connected" ) {
            $headerPattern = '/sessionid=(.*?);/';

            $this->setURL( "https://instagram.com/accounts/logout/" );
            $this->setReferer( "https://instagram.com/" );
            $this->setHeader();
            $this->setPostData( array( 'csrfmiddlewaretoken' => $this->getToken() ) );
            $response = $this->submit();

            preg_match( $headerPattern, $response['header'], $matches, PREG_OFFSET_CAPTURE );

            if ( $matches[1][0] == $this->getSessionID() ) {
                $this->ErrorMSG( "Can't logout." );
                return false;
            } else {
                if ( file_exists( $this->getSessionFile() ) ) {
                    unlink( $this->getSessionFile() );
                }
                $this->setSessionID();
                $this->setStatus( "Disconnected" );
                $this->setUsername( "" );
                return true;
            }
        } else {
            $this->ErrorMSG( "You are not logged in." );
            return false;
        }

    }

    // Set URL
    private function setURL( $URL = "" )
    {
        $this->curlURL = $URL;
        return $this->curlURL;
    }

    // Set Referer
    private function setReferer( $referer = "" )
    {
        $this->curlReferer = $referer;
        return $this->curlReferer;
    }

    // Set User Agent
    public function setUserAgent( $userAgent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.124 Safari/537.36" )
    {
        $this->userAgent = preg_replace( '/\s+/', ' ', $userAgent );
        return $this->userAgent;
    }

    // Set Token
    private function setToken( $Token = "" )
    {
        if ( trim( $Token ) != "" ) {
            $this->curlToken = $Token;
        } else {
            $this->curlToken = md5( rand( 1, 5000 ) );
        }
        return $this->curlToken;
    }

    // Set Header
    private function setHeader( $string = "" )
    {
        if ( trim( $string ) != "" ) {
            $this->curlHeader[] = $string;
        } else {
            unset( $this->curlHeader );
        }
        return $this->curlHeader;
    }

    // Set Post Data
    private function setPostData( $dataArray = "" )
    {
        if ( is_array( $dataArray ) ) {
            if ( is_array( $this->curlPostData ) ) {
                $this->curlPostData = array_merge( $this->curlPostData, $dataArray );
            } else {
                $this->curlPostData = $dataArray;
            }

        } else {
            unset( $this->curlPostData );
        }
        return $this->curlPostData;
    }

    // Set Session ID
    private function setSessionID( $sessionID = "" )
    {
        $this->sessionID = $sessionID;
        return $this->sessionID;
    }

    // Set Status
    private function setStatus( $status = "" )
    {
        $this->status = $status;
        return $this->status;
    }

    // Set Username
    private function setUsername( $username = "" )
    {
        $this->username = $username;
        return $this->username;
    }

    // Set Show Errors
    public function setShowErrors( $trueOrFalse = true )
    {
        if ( $trueOrFalse == false ) {
            $this->showErrors = false;
        } else {
            $this->showErrors = true;
        }
        return $this->showErrors;
    }

    // Get Session File
    private function getSessionFile( $username = "" )
    {
        if ( $username != "" ) {
            $sFile = 'savedsessions/' . md5( $username . "uh3GtefK&%3@" ) . ".php";
        } else {
            $sFile = 'savedsessions/' . md5( $this->getUsername() . "uh3GtefK&%3@" ) . ".php";
        }

        return $sFile;
    }

    // Get Username
    public function getUsername()
    {
        return $this->username;
    }

    // Get Status
    public function getStatus()
    {
        return $this->status;
    }

    // Get URL
    private function getURL()
    {
        return $this->curlURL;
    }

    // Get Referer
    private function getReferer()
    {
        return $this->curlReferer;
    }

    // Get Header
    private function getHeader()
    {
        return $this->curlHeader;
    }

    // Get Post Data
    private function getPostData()
    {
        return $this->curlPostData;
    }

    // Get User Agent
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    // Get Session ID
    private function getSessionID()
    {
        return $this->sessionID;
    }

    // Get Show Errors
    public function getShowErrors()
    {
        return $this->showErrors;
    }

    // Get Last Error
    public function getLastError()
    {
        return $this->lastError;
    }

    // Get Token
    private function getToken()
    {
        return $this->curlToken;
    }

    private function submit()
    {

        $headers = $this->getHeader();
        if ( $this->getReferer() != "" ) {
            $headers[] = "Referer: " . $this->getReferer();
        }

        if ( $this->getSessionID() != "" ) {
            $headers[] = "Cookie: sessionid=" . $this->getSessionID() . "; csrftoken=" . $this->getToken();
        } else {
            $headers[] = "Cookie: csrftoken=" . $this->getToken();
        }
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $this->getURL() );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
        curl_setopt( $ch, CURLOPT_HEADER, 1 );
        if ( $this->getUserAgent() != "" ) {
            curl_setopt( $ch, CURLOPT_USERAGENT, $this->getUserAgent() );
        }
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        if ( count( $this->getPostData() ) > 0 ) {
            curl_setopt( $ch, CURLOPT_POST, 1 );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $this->getPostData() ) );
        }

        $output = curl_exec( $ch );
        $headerSize = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $response['header'] = substr( $output, 0, $headerSize );
        $response['body'] = substr( $output, $headerSize );
        curl_close( $ch );

        $this->setPostData();
        $this->setToken();

        return $response;
    }

    // Show error messages
    private function ErrorMSG( $string = "" )
    {
        $this->lastError = "Error: " . $string . ".";
        if ( $this->showErrors === true ) {
            echo $this->lastError . "\n<br />";
        }
        return $this->lastError;
    }

}

?>