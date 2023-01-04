# APInsta
This class was made as a workaround for Instagram Notification API.

__IMPORTANT__: This was not made to be a secure API as it uses the plain-text username/password for authentication, and it will save the user session ID in the directory 'savedsessions' (Optional).

I was building this for my own use to save notification in a file so I can keep track of all comments on my account. ~~I'm planning to add the rest of Instagram APIs in this class, but I'm busy at the moment so it will take some time. If anyone would like to do it I'll be more than happy.~~

__NOTE__: The API will not return the URL to the Picture/Video, instead it will return the media ID, to get the URL, you will need to use the [Media Endpoints API](https://instagram.com/developer/endpoints/media/#get_media) as below:
```
https://api.instagram.com/v1/media/{media-id}?access_token=ACCESS-TOKEN
```

Use:-
```php
<?php
include('APInsta.class.php');
$insta = new \APInsta\Instagram();
$insta->login("Username", "Password", true);
$json = $insta->getNotifications();
// To logout and delete the saved session ID use:-
// $insta->logout();
?>
```
