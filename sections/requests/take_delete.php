<?php
//******************************************************************************//
//--------------- Delete request -----------------------------------------------//

authorize();

[$RequestID, $UserID, $Title, $CategoryID, $GroupID] = $DB->row("
    SELECT ID,
        UserID,
        Title,
        CategoryID,
        GroupID
    FROM requests
    WHERE ID = ?
    ", (int)$_POST['id']
);
if (is_null($RequestID)) {
    error(404);
}

if ($LoggedUser['ID'] != $UserID && !check_perms('site_moderate_requests')) {
    error(403);
}

$CategoryName = $Categories[$CategoryID - 1];

//Do we need to get artists?
if ($CategoryName === 'Music') {
    $ArtistForm = Requests::get_artists($RequestID);
    $ArtistName = Artists::display_artists($ArtistForm, false, true);
    $FullName = $ArtistName.$Title;
} else {
    $FullName = $Title;
}

// Delete request, votes and tags
$DB->prepared_query('DELETE FROM requests WHERE ID = ?', $RequestID);
$DB->prepared_query('DELETE FROM requests_votes WHERE RequestID = ?', $RequestID);
$DB->prepared_query('DELETE FROM requests_tags WHERE RequestID = ?', $RequestID);

$DB->prepared_query("
    SELECT ArtistID FROM requests_artists WHERE RequestID = ?
    ", $RequestID
);
$RequestArtists = $DB->collect(0);
foreach ($RequestArtists as $RequestArtist) {
    $Cache->delete_value("artists_requests_$RequestArtist");
}
$DB->prepared_query('
    DELETE FROM requests_artists
    WHERE RequestID = ?', $RequestID);
$Cache->delete_value("request_artists_$RequestID");

$DB->prepared_query('
    REPLACE INTO sphinx_requests_delta
        (ID)
    VALUES
        (?)', $RequestID);

(new \Gazelle\Manager\Comment)->remove('requests', $RequestID);

if ($UserID != $LoggedUser['ID']) {
    (new Gazelle\Manager\User)->sendPM($UserID, 0,
        'A request you created has been deleted',
        "The request \"$FullName\" was deleted by [url=user.php?id={$LoggedUser['ID']}]"
            . $LoggedUser['Username'].'[/url] for the reason: [quote]'.$_POST['reason'].'[/quote]'
    );
}

(new Gazelle\Log)->general("Request $RequestID ($FullName) was deleted by user ".$LoggedUser['ID'].' ('.$LoggedUser['Username'].') for the reason: '.$_POST['reason']);

$Cache->delete_value("request_$RequestID");
$Cache->delete_value("request_votes_$RequestID");
if ($GroupID) {
    $Cache->delete_value("requests_group_$GroupID");
}

header('Location: requests.php');
