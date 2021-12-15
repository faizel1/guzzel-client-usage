<?php

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;

defined('BASEPATH') or exit('No direct script access allowed');

class Welcome extends CI_Controller
{

    public function login()
    {
        $url = 'http://192.168.0.110:9004/v1';
        $data = array('userId' => 'Master', 'password' => '0000', 'userType' => 2);

        // file to store cookie data
        $cookieFile = 'cookie_jar.txt';

        $cookieJar = new FileCookieJar($cookieFile, TRUE);

        $client = new Client([
            'base_uri' => $url,
            // specify the cookie jar
            'cookies' => $cookieJar
        ]);


        // guzzle/cookie.php, a page that returns cookies.

        $response = $client->request('POST', $url . '/login', [
            'body' => json_encode($data),
            'cookies' => $cookieJar,
            'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],

        ]);


        return $response->getBody()->read(1024);
    }



    public function users()
    {
        $url = 'http://192.168.0.110:9004/v1';
        // $data = array('userId' => 'Master', 'password' => '0000', 'userType' => 2);

        // file to store cookie data
        $cookieFile = 'cookie_jar.txt';

        $cookieJar = new FileCookieJar($cookieFile, TRUE);

        $client = new Client([
            'base_uri' => $url,
            // specify the cookie jar
            'cookies' => $cookieJar
        ]);




        // guzzle/cookie.php, a page that returns cookies.

        $response = $client->request('GET', $url . '/users?groupID=0&subInclude=true&offset=0&limit=10', [
            'cookies' => $cookieJar
        ]);

        return $response->getBody()->read(1024);
    }



    public function index()
    {

        $unregistered_id = [];
        $file_not_found = [];

        $user_registered = 0;

        $result = new stdClass();
        $UserFaceWTInfo = new stdClass();
        $url = 'http://192.168.0.110:9004/v1';
        // return    $this->login($url);


        $User = $this->db->select("id as ID,name as Name,image ")->from('users')->where('status', 0)->get()->result();

        // check if there isn't new data to register
        if (count($User) <= 0) {
            echo "No new data found to upload";
            return;
        }

        //login page to the site
        $this->login($url);

        foreach ($User as $key => $value) {
            $absoluteImagePath = false;
            $id = explode("/", $value->ID);
            $value->ID = $id[2];
            $id = implode("/", $id);

            $imageId = str_replace('/', '', $id);
            $date = date('Y-m-d H:i:s');

            $value->ExpireDate = $date;
            $value->RegistDate = $date;
            $value->UniqueId = $value->ID;

            $value->VerifyLevel = 0;
            $value->Privilege = 2;
            $value->UsePeriodFlag = 0;
            $value->AuthInfo = [9, 0, 0, 0, 0, 0, 0, 0];
            $value->DuressFinger = [0, 0, 0, 0, 0, 0, 0, 0];

            // absolute path to the image

            if (is_null($value->image)) {
                $absoluteImagePath = FCPATH . "uploads/images/" . $imageId . ".JPG";
            } elseif ($value->image == '?') {
                $base64 = '';
                $fileSize = 1;
            } else {
                $absoluteImagePath = FCPATH . "uploads/images/" . "IMG_" . $value->image . ".JPG";
            }



            //check if the file exist, if not skip the loop
            if ($absoluteImagePath) {
                if (!file_exists($absoluteImagePath)) {
                    $file_not_found[] = $id . " " . $value->Name . "  " . $file = $value->image ?? $imageId;

                    continue;
                } else {
                    //get the size of the image
                    $fileSize = filesize($absoluteImagePath);

                    // compress the image if it is greater than 1mb
                    if ($fileSize > 500000) {
                        $this->compress_image($absoluteImagePath, 640, 1060);
                    }

                    $imagedata = file_get_contents($absoluteImagePath);
                    $base64 =  base64_encode($imagedata);
                }
            }


            $UserFaceWTInfo->UserID =  $value->ID;
            $UserFaceWTInfo->TemplateSize = $fileSize;
            $UserFaceWTInfo->TemplateData = $base64;
            $UserFaceWTInfo->TemplateType = 1;

            $result->UserInfo = $value;
            $result->UserFaceWTInfo[0] = $UserFaceWTInfo;

            // register the data to the api
            $ApiResponse = json_decode($this->register_user($url, $value->ID, $result));

            //update registered user
            if ($ApiResponse->Result->ResultCode == 0) {
                $this->update_status("users", $id);
                $user_registered++;
            } else {
                $unregistered_id[] = $id . " " . $value->Name . " -> " . $ApiResponse->Result->ResultCode;
            }
        }
        echo " New users Found => " . count($User) . "<br />";
        echo " Registered Users => " . $user_registered . "<br />";
        echo " total Unregistered Users => " . (count($User) - $user_registered) . "<br />";

        echo "<br /><br /> List of Unregistered users <br /> ";
        foreach ($unregistered_id as $key => $value) {
            echo $key, " --- " . $value . "<br />";
        }

        echo "<br /><br /> List of users  which their image could not be found<br /> ";
        foreach ($file_not_found as $key => $value) {
            echo $key, " --- " . $value . "<br />";
        }
    }



    public function register_user($url, $id, $result)
    {

        // file to store cookie data
        $cookieFile = 'cookie_jar.txt';

        $cookieJar = new FileCookieJar($cookieFile, TRUE);

        $client = new Client([

            'base_uri' => $url,
            // specify the cookie jar file
            'cookies' => $cookieJar
        ]);


        $response = $client->request('POST', $url . "/users?UserID=$id ", [

            'json' => $result,
            'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
        ]);

        return $response->getBody()->read(1024);
    }


    public function delete($id)
    {
        $url = 'http://192.168.0.110:9004/v1';

        $this->login();

            // file to store cookie data
            $cookieFile = 'cookie_jar.txt';

            $cookieJar = new FileCookieJar($cookieFile, true);

            $client = new Client([

                'base_uri' => $url,
                // specify the cookie jar
                'cookies' => $cookieJar
            ]);


            $response = $client->request('DELETE', $url . "/users/" . stripslashes($id), [

                'headers'  => ['content-type' => 'application/json', 'Accept' => 'application/json'],
            ]);

            $res[] = $response->getBody()->read(1024);
        
    }



    public function compress_image($path, $height, $width)
    {
        $this->load->library('image_lib');

        $config['image_library'] = 'gd2';
        $config['source_image'] = $path;
        $config['maintain_ratio'] = true;
        $config['height']       = $height;
        $config['width']         = $width;

        $this->image_lib->clear();
        $this->image_lib->initialize($config);
        $this->image_lib->resize();


        $config = array();
        $config['image_library']   = 'gd2';
        $config['source_image'] = $path;
        $config['rotation_angle'] = 270;

        $this->image_lib->clear();
        $this->image_lib->initialize($config);
        $this->image_lib->rotate();
    }

    public function update_status($table, $id)
    {
        $this->db->where('id', $id);
        $this->db->update($table, ["status" => 1]);
    }
}
