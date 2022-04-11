<?php

namespace App\Helpers;

use App\Device;
use App\Notification;
use App\User;
use File;
use Illuminate\Support\Facades\Storage;
use Str;
use Edujugon\PushNotification\PushNotification;

function sendNotification($title, $message, $notification_type, $task_id = 1, $user_id, $date = null, $event_id = null)
{
    $user = User::find($user_id);
    $count = Notification::where('user_id', $user_id)->where('is_read', 0)->count();
    if ($user->is_subscribed == 1) {
        //notification_type = 1 for event ,2 = friend request type
        $push = new PushNotification('fcm');

        $devices = Device::where('user_id', $user_id)->get();
        if (count($devices) > 0) {
            foreach ($devices as $deviceType) {
                $push_token = $deviceType->push_token;
                // if($deviceType->type == 'ios')
                // {
                //       $push->setMessage([
                //         "content_available" => true,
                //         "mutable_content" => true,
                //         'data' => [
                //             "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                //             'title' => $title,
                //             'body' => $message,
                //             "type" => "Event",
                //             'date' => $date,
                //             "content"=> [
                //                 "payload" => [
                //                     "type" => "Event",
                //                     'event_id' => $event_id
                //                 ]
                //                 ],
                //             'notification_type' => $notification_type,
                //             'task_id' => $task_id,
                //             'event_id' => $event_id
                //         ],
                //     ])
                //         ->setDevicesToken([$push_token])
                //         ->send()
                //         ->getFeedback();
                // }else{
                $push->setMessage([
                    "content_available" => true,
                    'notification' => [
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                        'title' => $title,
                        'body' => $message,
                        "type" => "Event",
                        'date' => $date,
                        'notification_type' => $notification_type,
                        'task_id' => $task_id,
                        "content_available" => true,
                        'event_id' => $event_id,
                        'badge' => $count
                    ],
                    'data' => [
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                        'title' => $title,
                        'body' => $message,
                        "type" => "Event",
                        'date' => $date,
                        'notification_type' => $notification_type,
                        'task_id' => $task_id,
                        'event_id' => $event_id,
                        'badge' => $count
                    ],
                ])
                    ->setDevicesToken([$push_token])
                    ->send()
                    ->getFeedback();
            }
            // }
        }
    }
}

function chat_notification($name, $profile, $sender_id, $receiver_id, $title, $notification_type, $message, $task_id = 3, $group_id)
{
    $user = User::find($receiver_id);
    $count = Notification::where('user_id', $receiver_id)->where('is_read', 0)->count();
    if ($user->is_subscribed == 1) {
        // notification_type = 3 for chat
        $push = new PushNotification('fcm');

        $devices = Device::where('user_id', $receiver_id)->get();

        if (count($devices) > 0) {
            foreach ($devices as $deviceType) {
                $push_token = $deviceType->push_token;
                $push->setMessage([
                    "content_available" => true,
                    'notification' => [
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                        'title' => $title,
                        'body' => $message,
                        "type" => "Chat",
                        'name' => $name,
                        'profile' => $profile,
                        'sender_id' => $sender_id,
                        'group_id' => $group_id,
                        'notification_type' => $notification_type,
                        'task_id' => $task_id,
                        "content_available" => true,
                        'badge' => $count
                    ],
                    'data' => [
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                        'title' => $title,
                        'body' => $message,
                        "type" => "Chat",
                        'name' => $name,
                        'profile' => $profile,
                        'sender_id' => $sender_id,
                        'group_id' => $group_id,
                        'notification_type' => $notification_type,
                        'task_id' => $task_id,
                        'badge' => $count
                    ],
                ])
                    ->setDevicesToken([$push_token])
                    ->send()
                    ->getFeedback();
            }
        }
    }
}

function add_notification($user_id, $title, $message, $type, $date = null)
{
    $notification = new Notification();
    $notification->user_id = $user_id;
    $notification->title = $title;
    $notification->message = $message;
    $notification->type = $type;
    if ($date != null) {
        $notification->date = date('Y-m-d', strtotime($date));
    }
    $notification->save();

    return true;
}

function sendCampaignNotification($title, $user_id, $notification_type = 4, $task_id = 1)
{
    // notification_type == 4 for campaign
    $push = new PushNotification('fcm');
    $devices = Device::where('user_id', $user_id)->get();
    if (count($devices) > 0) {
        foreach ($devices as $deviceType) {
            $push_token = $deviceType->push_token;
            $push->setMessage([
                "content_available" => true,
                'notification' => [
                    "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                    'title' => $title,
                    'body' => 'New Campaign Available',
                    "type" => "Campaign",
                    'notification_type' => $notification_type,
                    'task_id' => $task_id,
                    "content_available" => true
                ],
                'data' => [
                    "click_action" => "FLUTTER_NOTIFICATION_CLICK",
                    'title' => $title,
                    'body' => 'New Campaign Available',
                    "type" => "Campaign",
                    'notification_type' => $notification_type,
                    'task_id' => $task_id
                ],
            ])
                ->setDevicesToken([$push_token])
                ->send()
                ->getFeedback();
        }
        // }
    }
}

function convertDistanceToTime($distance)
{
    $time = $distance / 3; // we assume that one person average walk 3 km per hour
    // $remainder = $time % 1;
    // $minute_time = 60 * $remainder;
    // $hour_time = (int)$time;
    return sprintf('%02d:%02d', (int) $time, fmod($time, 1) * 60);
}


function distance($lat1, $lon1, $lat2, $lon2, $unit)
{
    if (($lat1 == $lat2) && ($lon1 == $lon2)) {
        return 0;
    } else {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }
}

function generate_api_token($mobile_number)
{
    return md5(time() . Str::random(30)) . md5($mobile_number) . md5(Str::random(30) . time());
}

function commonUploadImage($storage_path, $file_path)
{
    $storage_path = 'public/' . $storage_path;
    $file_store_path = Storage::disk('local')->put($storage_path, $file_path);
    return $file_store_path;
}

function download_remote_file($file_url, $save_to)
{
    $content = file_get_contents($file_url);
    if (!empty($content)) {
        file_put_contents($save_to, $content);
        return true;
    }
    return false;
}

function deleteOldImage($file_path)
{
    if ($file_path != "storage/no-image.jpg") {
        $file_delete = Storage::disk('local')->delete($file_path);
    }
    return true;
}

function array_random($array, $amount = 1)
{
    $keys = array_rand($array, $amount);

    if ($amount == 1) {
        return $array[$keys];
    }

    $results = [];
    foreach ($keys as $key) {
        $results[] = $array[$key];
    }

    return $results;
}

function getUploadImage($storage_path)
{
    return Storage::url($storage_path);
}

function get_file_extension($file_name)
{
    return substr(strrchr($file_name, '.'), 1);
}

function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return strtoupper($randomString);
}

function is_json($string, $return_data = false)
{
    $data = json_decode($string, true);
    return (json_last_error() == JSON_ERROR_NONE) ? ($return_data ? $data : TRUE) : FALSE;
}

function is_arary_json($string, $return_data = false)
{
    $data = json_decode($string, true);
    return (json_last_error() == JSON_ERROR_NONE) ? ($return_data ? $data : TRUE) : FALSE;
}

function file_exists_check($path)
{
    return file_exists($path);
}

function add_prefix_path($n, $image_path) // function for delete images
{
    return ($image_path . $n);
}

function empty_dir($dir_path)
{
    array_map('unlink', glob($dir_path . '*'));
}

function sort_by_order($a, $b)
{
    return $a['i_order'] - $b['i_order'];
}

function remove_dir($dir_path)
{
    File::deleteDirectory($dir_path);
}

/**Date format */
function getApiDateFormat($date)
{
    return gmdate('Y-m-d h:i:s', strtotime($date));
}

/**image path  */
function getApiImagePath($path)
{
    return  asset('storage/app/' . $path);
}

/** 12 Hours Time  */
function getApiTimeFormat($time)
{
    return gmdate('h:i A', strtotime($time));
}

function custom_number_format($n, $precision = 2)
{
    if ($n < 900) {
        // Default
        $n_format = number_format($n);
    } else if ($n < 900000) {
        // Thausand
        $n_format = number_format($n / 1000, $precision) . 'K';
    } else if ($n < 900000000) {
        // Million
        $n_format = number_format($n / 1000000, $precision) . 'M';
    } else if ($n < 900000000000) {
        // Billion
        $n_format = number_format($n / 1000000000, $precision) . 'B';
    } else {
        // Trillion
        $n_format = number_format($n / 1000000000000, $precision) . 'T';
    }
    return $n_format;
}
