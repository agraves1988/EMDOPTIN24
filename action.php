<?php

define("API_URL", "https://api.zenotistage.com");
define("API_KEY", "ff0ac92fd19a4ebb97a6ce3b00a4a9e1865a702f8f294c0faddc7af4c49e3c4c");
define('CENTER_IDS', [
    'ESTHETIXMD SPA' => 'c0a9f0cc-7693-4b6d-b428-2f780a258460'
]);

function checkIfStringValuesAreEmpty($array)
{
    foreach ($array as $value) {
        if (is_string($value) and trim($value) == "") {
            return true;
        }
    }
    return false;
}

function getOptInPreferences($emailOptIn, $smsOptIn)
{
    $optInPreferences = [
        "emailOptIn" => false,
        "smsOptIn" => false
    ];

    if ((bool) $emailOptIn) {
        $optInPreferences["emailOptIn"] = true;
    }
    if ((bool) $smsOptIn) {
        $optInPreferences["smsOptIn"] = true;
    }

    return $optInPreferences;

}

function apiCall($apiURL, $apiMethod, $apiPayLoad, $apiName)
{

    try {
        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Accept: application/json",
                    "Authorization: apikey " . API_KEY
                ],
                'method' => $apiMethod,
                'content' => json_encode($apiPayLoad),
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($apiURL, false, $context);

    } catch (Exception $e) {
        echo $apiName . " API call(s) failed, please try again!!";
    }
    if ($response === false) {
        echo $apiName . " API call(s) failed, please try again!";
    } else {
        $response = json_decode($response, true);
    }
    return $response;
}

function searchGuest($email, $phone)
{
    $searchGuestURL = API_URL . "/v1/guests/search?email=" . $email . "&phone=" . $phone;
    $searchGuestResponse = apiCall($searchGuestURL, "GET", "", "Guest Search");
    return $searchGuestResponse;
}

function retrieveGuest($guestId)
{
    $retrieveGuestURL = API_URL . "/v1/guests/" . $guestId . "?expand=preferences&expand=address_info&expand=tags";
    $retrieveGuestResponse = apiCall($retrieveGuestURL, "GET", "", "Guest Retrieval");

    if ($retrieveGuestResponse and array_key_exists("id", $retrieveGuestResponse)) {
        return $retrieveGuestResponse;
    } else {
        echo "Guest could not be retrieved, please try again!";
    }
}

function updateGuest($guestObject, $input)
{
    $updateGuestURL = API_URL . "/v1/guests/" . $guestObject["id"];

    $guestTags = $guestObject["tags"];
    if (is_null($guestTags) || empty($guestTags)) {
        $guestObject["tags"] = ["OnlineOptin"];
    } elseif (!in_array("OnlineOptin", $guestTags)) {
        array_push($guestObject["tags"], "OnlineOptin");
    }

    $optInPreferences = getOptInPreferences($input["emailOptIn"], $input["smsOptIn"]);
    $guestObject["preferences"]["receive_marketing_email"] = $optInPreferences["emailOptIn"];
    $guestObject["preferences"]["receive_marketing_sms"] = $optInPreferences["smsOptIn"];

    $updatedGuestResponse = apiCall($updateGuestURL, "PUT", $guestObject, "Guest Updation");
    if ($updatedGuestResponse and array_key_exists("id", $updatedGuestResponse)) {
        return $updatedGuestResponse["id"];
    } else {
        echo "Guest could not be updated, please try again!";
    }

}

function createGuest($input)
{
    $optInPreferences = getOptInPreferences($input["emailOptIn"], $input["smsOptIn"]);

    $newGuestData = new stdClass();
    $newGuestData->personal_info = new stdClass();
    $newGuestData->personal_info->mobile_phone = new stdClass();
    $newGuestData->preferences = new stdClass();
    $newGuestData->address_info = new stdClass();
    $newGuestData->center_id = CENTER_IDS[$input["location"]];
    $newGuestData->personal_info->first_name = $input['firstName'];
    $newGuestData->personal_info->last_name = $input['lastName'];
    $newGuestData->personal_info->email = $input['email'];
    $newGuestData->personal_info->mobile_phone->country_code = 225;
    $newGuestData->personal_info->mobile_phone->number = $input['phone'];
    $newGuestData->preferences->receive_transactional_email = true;
    $newGuestData->preferences->receive_transactional_sms = true;
    $newGuestData->preferences->receive_marketing_email = $optInPreferences["emailOptIn"];
    $newGuestData->preferences->receive_marketing_sms = $optInPreferences["smsOptIn"];
    $newGuestData->address_info->country_id = 225;
    $newGuestData->tags = ["OnlineOptin"];

    $guestCreateURL = API_URL . "/v1/guests";
    $guestCreationResponse = apiCall($guestCreateURL, "POST", $newGuestData, "Guest Creation");

    if ($guestCreationResponse and array_key_exists("id", $guestCreationResponse)) {
        return $guestCreationResponse["id"];
    } else {
        echo $guestCreationResponse["Message"];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $jsonData = file_get_contents('php://input');
    $input = json_decode($jsonData, true);

    $guestId = "";
    if (checkIfStringValuesAreEmpty($input)) {
        echo "Empty";
    } else {
        $guestResponse = searchGuest($input["email"], $input["phone"]);
        if ($guestResponse and array_key_exists("guests", $guestResponse)) {
            $guests = $guestResponse["guests"];
            $guestSearchCount = count($guests);
            if ($guestSearchCount == 0) {
                $guestId = createGuest($input);
            } elseif ($guestSearchCount == 1 and array_key_exists("id", $guests[0])) {
                $guestId = updateGuest(retrieveGuest($guests[0]["id"]), $input);
            } elseif ($guestSearchCount > 1) {
                echo "Duplicate";
            }
        }
    }
    if ($guestId != "") {
        echo "Success";
    }
} else {
    echo "Access Denied";
}