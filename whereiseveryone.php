<?php
/**
 * WhereIsEveryone
 * 
 * PHP version 5
 *
 * @category  FCMS
 * @package   FamilyConnections
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @copyright 2010 Haudenschilt LLC
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @link      http://www.familycms.com/wiki/
 * @since     2.4
 */
session_start();

define('URL_PREFIX', '');

require 'fcms.php';

load('datetime', 'whereiseveryone', 'foursquare');

// Check that the user is logged in
isLoggedIn();
$currentUserId = cleanInput($_SESSION['login_id'], 'int');

$whereObj = new WhereIsEveryone($currentUserId);

// Setup the Template variables;
$TMPL = array(
    'sitename'      => getSiteName(),
    'nav-link'      => getNavLinks(),
    'pagetitle'     => T_('Where Is Everyone'),
    'path'          => URL_PREFIX,
    'displayname'   => getUserDisplayName($currentUserId),
    'version'       => getCurrentVersion(),
    'year'          => date('Y')
);
$TMPL['javascript'] = '
<script type="text/javascript" src="'.$TMPL['path'].'inc/js/livevalidation.js"></script>
<script type="text/javascript">Event.observe(window, "load", function() { initChatBar(\''.T_('Chat').'\', \''.$TMPL['path'].'\'); });</script>';

// Show Header
require_once getTheme($currentUserId).'header.php';

echo '
        <div id="whereiseveryone-page" class="centercontent clearfix">';
// TODO
// Add the following info to documentation/help
// If you receive the following error, its because your site isn't using php 5
//      Parse error: syntax error, unexpected '{' in

//-------------------------------------
// Show Latest checkins
//-------------------------------------
$users  = $whereObj->getActiveUsers();
$config = $whereObj->getFoursquareConfigData();

if (count($users[0]) <= 0)
{
    echo '
            <div class="info-alert">
                <p>'.T_('No users with foursquare data found.').'</p>
            </div>';
    return;
}

// Foursquare hasn't been setup or is invalid
if (empty($config['fs_client_id']) or empty($config['fs_client_secret']))
{
    // If admin is viewing, alert them that the config is missing/messed up
    if (checkAccess($currentUserId) < 2)
    {
        echo '
            <div class="info-alert">
                <h2>'.T_('Foursquare is not configured correctly.').'</h2>
                <p>'.T_('The "Where Is Everyone" feature cannot work without Foursquare.  Please configure Foursquare or turn off "Where Is Everyone".').'</p>
            </div>';
    }
    // we continue on, because we still might be able to show user data (if they have an access token)
    // this would happen if foursquare was setup, users granted access, then foursquare was removed.
}

$historyData = array();

$i = 0;
foreach ($users as $k => $data)
{
    // Skip users who don't have foursquare setup
    if (empty($data['access_token']))
    {
        continue;
    }

    $fsObj = new EpiFoursquare($config['fs_client_id'], $config['fs_client_secret'], $data['access_token']);

    try
    {
        $creds = $fsObj->get('/users/'.$data['user_id'].'/checkins');
    }
    catch(EpiFoursquareException $e)
    {
        echo 'We caught an EpiOAuthException';
        echo $e->getMessage();
        break;
    }
    catch(Exception $e)
    {
        echo 'We caught an unexpected Exception';
        echo $e->getMessage();
        break;
    }

    $photo = getAvatarPath($data['avatar'], $data['gravatar']);

    foreach ($creds->response->checkins->items as $checkin)
    {
        // Skip shouts, etc
        if ($checkin->type != 'checkin')
        {
            continue;
        }

        $address = isset($checkin->venue->location->address) ? $checkin->venue->location->address : '';

        $date = fixDate('F j, Y', $data['timezone'], date('Y-m-d H:i:s', $checkin->createdAt));
        $sort = $checkin->createdAt;

        $historyData[$i] = array(
            'photo'     => $photo,
            'name'      => $data['name'],
            'venue'     => $checkin->venue->name,
            'address'   => $address,
            'date'      => $date,
            'sort'      => $sort
        );
        $i++;
    }
}

$historyData = subval_sort($historyData, 'sort');
$historyData = array_reverse($historyData);

// Print results
echo '
            <ul id="latest-history">
                <li id="label">'.T_('History').'</li>';

foreach ($historyData as $k => $data)
{
    echo '
                <li>
                    <div class="img"><img src="'.$data['photo'].'" height="32px" width="32px"/></div>
                    <div class="user">'.$data['name'].'</div>
                    <div class="checkin">
                        <a href="#">'.$data['venue'].'</a>
                        <span>'.$data['address'].'</span>
                        '.$data['date'].'
                    </div>
                </li>';
}

echo '
            </ul>
        </div><!-- #whereiseveryone-page .centercontent -->';

// Show Footer
require_once getTheme($currentUserId).'footer.php';