Route::post('getInbox', 'ChatController@getInbox');
Route::post('getMessages', 'ChatController@getMessages');
Route::post('sendMessage', 'ChatController@sendMessage');
Route::post('sendFile', 'ChatController@sendFile');
Route::post('setReadMessage1', 'ChatController@setReadMessage1');
Route::post('deleteMessage', 'ChatController@deleteMessage');