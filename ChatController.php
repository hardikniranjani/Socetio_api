<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Users;
use App\Helpers\CommonApiHelper;
use Illuminate\Http\Request;
use Hash, Input, Session, Redirect, Mail, URL, Str, Config, Response, View;
use App\GroupMsg;
use App\GroupMst;
use App\Device;
use App\FriendRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\User;
use Laravel\Passport\HasApiTokens;

use function App\Helpers\add_notification;
use function App\Helpers\getUploadImage;
use function App\Helpers\sendNotification;
use function App\Helpers\getApiDateFormat;
use function App\Helpers\generateRandomString;
use function App\Helpers\chat_notification;

class ChatController extends Controller
{
    public function getInbox(Request $request)
    {
        try {
            $user_id = Auth::user()->id;
            //dd($user_id);
            $this->UpdateCurrentActive($user_id);

            $already_friend = FriendRequest::where([['receiver_id', '=', $user_id], ['is_friend', '=', 1]])->orWhere([['sender_id', '=', $user_id], ['is_friend', '=', 1]])->pluck('receiver_id')->toArray();
            $already_friends_other = FriendRequest::where([['receiver_id', '=', $user_id], ['is_friend', '=', 1]])->orWhere([['sender_id', '=', $user_id], ['is_friend', '=', 1]])->pluck('sender_id')->toArray();
            $already_friend = array_diff($already_friend, array($user_id));
            $already_friends_other = array_diff($already_friends_other, array($user_id));
            //dd($already_friend);
            $users = User::where('role_id', 2)->where('id', '!=', $user_id)->select('id', 'first_name', 'last_name', 'profile', 'last_active_time', 'updated_at')->whereIn('id', $already_friend)->orWhereIn('id', $already_friends_other)->orderBy('updated_at', 'desc')->get();
            if (!empty($users)) {
                sleep(0.5);
            } else {
                sleep(2);
            }
            $getUsers = $users->map(function ($item) use ($user_id) {
                if (!empty($item->id)) {
                    $receiver_id = $item->id;
                    $item->last_active_time = (string) $this->getLastActiveTime($item->updated_at, $item->id);
                    $item->profile = getUploadImage($item->profile);
                    $item->unread_count = $this->getUnreadMessageCount($receiver_id);
                    $item->group_id = $this->getOrCreateGroup($user_id, $receiver_id);
                    $lastmessage = GroupMsg::where(['group_id' => $item->group_id, 'type' => 1])->orderBy('id', 'desc')->first();

                    if (!empty($lastmessage)) {
                        $item->last_msg = $lastmessage->message;
                        $item->message_time = $lastmessage->created_at;
                    } else {
                        $item->last_msg = '';
                        $item->message_time = null;
                    }
                    return $item;
                }
            });

            if (!empty($getUsers)) {
                $c = collect($getUsers);
                $oldgetUsers = $c->sortByDesc('message_time');
                $personal = collect();
                $getUsers = $personal->merge($oldgetUsers);
            }
            // dd($getUsers);

            return response()->json(['success' => 1, 'users' => $getUsers]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }
    public function getMessages(Request $request)
    {
        try {
            $group_id = $request->group_id;
            $messageData = GroupMsg::with('user')->where(['group_id' => $group_id])->get();
            if ($messageData->isEmpty()) {
                $messageData = [];
            } else {
                GroupMsg::where('group_id', '=', $group_id)->update(['is_read' => 1]);
                $messageData->map(function ($item) {
                    $item->created_at = getApiDateFormat($item->created_at);
                    $item->updated_at = getApiDateFormat($item->updated_at);
                    $item->chat_time = date('h : i A | M d', strtotime($item->created_at));
                    $item->webfile = $item->filename;
                    $item->filename = URL::to('/') . '/' . $item->filename;
                    return $item;
                });
            }
            return response()->json(['success' => 1, 'messageData' => $messageData]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }
    public function sendMessage(Request $request)
    {
        try {
            $objGroupMsg = new GroupMsg();
            $objGroupMsg->user_id = Auth::user()->id;
            $objGroupMsg->group_id = $request->group_id;
            $objGroupMsg->message = $request->message;
            $objGroupMsg->type = $request->type;
            $objGroupMsg->save();

            $objGroupMsg->created_at = getApiDateFormat($objGroupMsg->created_at);
            $objGroupMsg->updated_at = getApiDateFormat($objGroupMsg->updated_at);
            $objGroupMsg->chat_time = date('h : i A | M d', strtotime($objGroupMsg->created_at));
            $objGroupMsg->webfile = '';
            $objGroupMsg->filename = '';
            $objGroupMsg->user->first_name = Auth::user()->first_name;

            // $receiver = GroupMst::find($request->group_id);

            $msg = $request->message;
            $group_id =  $request->group_id;
            $receiver_id =  $request->receiver_id;
            $name = Auth::user()->first_name . ' ' . Auth::user()->last_name;
            $title = $name;
            $profile = getUploadImage(Auth::user()->profile);
            $save = add_notification($receiver_id, "New Chat Message", $msg, $type = 3);
            $check = chat_notification($name, $profile, Auth::user()->id, $receiver_id, $title, $notification_type = 3, $msg, $task_id = 3, $group_id);

            return response()->json(['success' => 1, 'result' => $objGroupMsg]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }

    public function sendFile(Request $request)
    {
        try {
            $image_parts = explode(";base64,", $request['media']);
            $extension = explode('/', mime_content_type($request['media']))[1];
            $image_type_aux = explode("image/", $image_parts[0]);
            $image_type = $image_type_aux[1];

            /*  if(strpos($image_parts[0], 'image') === true) { 
                $image_type_aux = explode("image/", $image_parts[0]);
                $request->type = 2;
            }elseif(strpos($image_parts[0], 'video') === true) {
                $image_type_aux = explode("video/", $image_parts[0]);
                $request->type = 3;
            } */

            $image_base64 = base64_decode($image_parts[1]);
            $fileName = 'files/chat/' . generateRandomString() . '.' . $extension;
            $file = public_path() . '/' . $fileName;

            file_put_contents($file, $image_base64);

            $objGroupMsg = new GroupMsg();
            $objGroupMsg->user_id = Auth::user()->id;
            $objGroupMsg->group_id = $request->group_id;
            $objGroupMsg->filename = $fileName;
            $objGroupMsg->type = $request->type;
            $objGroupMsg->save();

            $objGroupMsg->created_at = getApiDateFormat($objGroupMsg->created_at);
            $objGroupMsg->updated_at = getApiDateFormat($objGroupMsg->updated_at);
            $objGroupMsg->chat_time = date('h : i A | M d', strtotime($objGroupMsg->created_at));
            $objGroupMsg->webfile = $fileName;
            $objGroupMsg->filename = URL::to('/') . '/' . $fileName;


            $msg = 'Image';
            $group_id =  $request->group_id;
            $name = Auth::user()->first_name . ' ' . Auth::user()->last_name;
            $profile = getUploadImage(Auth::user()->profile);
            $title = $name;

            $save = add_notification($request->receiver_id, "New Chat Image", $msg, $type = 3);
            $check = chat_notification($name, $profile, Auth::user()->id, $request->receiver_id, $title, $notification_type = 3, $msg, $task_id = 3, $group_id);

            return response()->json(['success' => 1, 'result' => $objGroupMsg]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }

    public  function setReadMessage1(Request $request)
    {
        try {
            $objGroupMsg = GroupMsg::find($request->msg_id);
            $objGroupMsg->is_read = 1;
            // $objGroupMsg->is_notification = 1;
            $objGroupMsg->save();
            return response()->json(['success' => 1, 'result' => $objGroupMsg]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }
    function getUnreadMessageCount($receiver_id)
    {
        $sender_id = Auth::user()->id;
        $receiver_id = $receiver_id;
        $group_info = GroupMst::where([['sender_id', '=', $sender_id], ['receiver_id', '=', $receiver_id]])->orWhere([['receiver_id', '=', $sender_id], ['sender_id', '=', $receiver_id]])->first();
        if (empty($group_info)) {
            $objGroupMst = new GroupMst;
            $objGroupMst->sender_id = $sender_id;
            $objGroupMst->receiver_id = $receiver_id;
            $objGroupMst->save();
            $group_info = $objGroupMst->id;
        }

        $user_id = $receiver_id;
        $messageCount = GroupMsg::where([['group_id', '=', $group_info->id], ['is_read', '=', 0], ['user_id', '!=', $sender_id]])->count();

        return $messageCount;
    }
    function UpdateCurrentActive($user_id)
    {
        try {
            $objSetActive = User::find($user_id);
            $objSetActive->last_active_time = 1;
            $objSetActive->save();
            return response()->json(['success' => 1, 'result' => $objSetActive]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }
    function getLastActiveTime($updateTime, $user_id)
    {
        try {
            $seconds  = strtotime(date('Y-m-d H:i:s')) - strtotime($updateTime);
            $secs = floor($seconds % 60);
            $objSetActive = User::find($user_id);
            // $last_active_time = 1;
            $last_active_time = date('h : i A | M d', strtotime($updateTime));
            if ($seconds > 20) {
                $objSetActive->last_active_time = 0;
                $objSetActive->save();
                $last_active_time = date('h : i A | M d', strtotime($objSetActive->updated_at));
            }


            return $last_active_time;

            //return response()->json(['success' => 1,'result' => $objSetActive]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }
    function getOrCreateGroup($sender_id, $receiver_id)
    {
        try {

            $sender_id = $sender_id;
            $receiver_id = $receiver_id;
            $group_id = GroupMst::where([['sender_id', '=', $sender_id], ['receiver_id', '=', $receiver_id]])->orWhere([['receiver_id', '=', $sender_id], ['sender_id', '=', $receiver_id]])->pluck('id')->first();

            if (empty($group_id)) {
                $objGroupMst = new GroupMst();
                $objGroupMst->sender_id = $sender_id;
                $objGroupMst->receiver_id = $receiver_id;
                $objGroupMst->save();
                $group_id = $objGroupMst->id;
            }
            return $group_id;

            //return response()->json(['success' => 1,'group_id' => $group_id]);
        } catch (\Exception $e) {
            return response()->json(['success' => 0, 'error' => $e->getMessage()]);
        }
    }
    // function calculate_time_span($date){
    //     $seconds  = strtotime(date('Y-m-d H:i:s')) - strtotime($date);

    //     $months = floor($seconds / (3600*24*30));
    //     $day = floor($seconds / (3600*24));
    //     $hours = floor($seconds / 3600);
    //     $mins = floor(($seconds - ($hours*3600)) / 60);
    //     $secs = floor($seconds % 60);

    //     if($seconds < 60)
    //         $time = $secs." seconds ago";
    //     else if($seconds < 60*60 )
    //         $time = $mins." min ago";
    //     else if($seconds < 24*60*60)
    //         $time = $hours." hours ago";
    //     else if($seconds < 24*60*60)
    //         $time = $day." day ago";
    //     else
    //         $time = $months." month ago";

    //     return $time;
    // }

    public function sendUnreadMessageNotification(Request $request)
    {
        //$id = $request->id;
        // $check =  GroupMsg::where('id',$request->id)->where('is_read',0)->first();
        // if(!empty($check)){
        $display_title =  trans('lanKey.add_chat');
        $display_msg = $request->message;
        $task_id =  $request->group_id;

        //$user_id =  $request->receiver_id;
        $check = sendNotification($user_id = 12, $display_title, $display_msg, $notification_type = 4, $task_id);
        //}
        return response()->json(['success' => 1, 'result' => $request->message]);
    }
}
