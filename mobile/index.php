<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Mobile app API
 */

// The name of the permission needed to log in.
// Set to null if you don't need it.
$access_permission = null;

require __DIR__ . "/../required.php";

require __DIR__ . "/../lib/login.php";

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Allow ping check without authentication
if ($VARS['action'] == "ping") {
    exit(json_encode(["status" => "OK"]));
}

function mobile_enabled() {
    $client = new GuzzleHttp\Client();

    $response = $client
            ->request('POST', PORTAL_API, [
        'form_params' => [
            'key' => PORTAL_KEY,
            'action' => "mobileenabled"
        ]
    ]);

    if ($response->getStatusCode() > 299) {
        return false;
    }

    $resp = json_decode($response->getBody(), TRUE);
    if ($resp['status'] == "OK" && $resp['mobile'] === TRUE) {
        return true;
    } else {
        return false;
    }
}

function mobile_valid($username, $code) {
    $client = new GuzzleHttp\Client();

    $response = $client
            ->request('POST', PORTAL_API, [
        'form_params' => [
            'key' => PORTAL_KEY,
            "code" => $code,
            "username" => $username,
            'action' => "mobilevalid"
        ]
    ]);

    if ($response->getStatusCode() > 299) {
        return false;
    }

    $resp = json_decode($response->getBody(), TRUE);
    if ($resp['status'] == "OK" && $resp['valid'] === TRUE) {
        return true;
    } else {
        return false;
    }
}

if (mobile_enabled() !== TRUE) {
    exit(json_encode(["status" => "ERROR", "msg" => lang("mobile login disabled", false)]));
}

// Make sure we have a username and access key
if (is_empty($VARS['username']) || is_empty($VARS['key'])) {
    http_response_code(401);
    die(json_encode(["status" => "ERROR", "msg" => "Missing username and/or access key."]));
}

// Make sure the username and key are actually legit
if (!mobile_valid($VARS['username'], $VARS['key'])) {
    engageRateLimit();
    http_response_code(401);
    die(json_encode(["status" => "ERROR", "msg" => "Invalid username and/or access key."]));
}

// Process the action
switch ($VARS['action']) {
    case "start_session":
        // Do a web login.
        if (user_exists($VARS['username'])) {
            if (get_account_status($VARS['username']) == "NORMAL") {
                if (authenticate_user($VARS['username'], $VARS['password'], $autherror)) {
                    if (is_null($access_permission) || account_has_permission($VARS['username'], $access_permission)) {
                        doLoginUser($VARS['username'], $VARS['password']);
                        $_SESSION['mobile'] = true;
                        exit(json_encode(["status" => "OK"]));
                    } else {
                        exit(json_encode(["status" => "ERROR", "msg" => lang("no admin permission", false)]));
                    }
                }
            }
        }
        exit(json_encode(["status" => "ERROR", "msg" => lang("login incorrect", false)]));
    default:
        http_response_code(404);
        die(json_encode(["status" => "ERROR", "msg" => "The requested action is not available."]));
}