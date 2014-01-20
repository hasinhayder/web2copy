<?php

namespace Barracuda\Copy;

/**
 * Copy API class
 *
 * @package Copy
 * @license https://raw.github.com/copy-app/php-client-library/master/LICENSE MIT
 */
class API
{

    const HEADER_DELIMTER = 0xba5eba11;
    const PART_DELIMITER = 0xcab005e5;
    const FINGERPRINT_SIZE = 73;
    const HEADER_STRUCT_SIZE = 24; // 6 * 4
    const PART_HEADER_STRUCT_SIZE = 105; // 8 * 4 + FINGERPRINT_SIZE

    /**
     * API URl
     * @var string $api_url
     */
    protected $api_url = 'http://api.copy.com';

    /**
     * Instance of OAuth
     * @var OAuth $oauth
     */
    private $oauth;

    /**
     * Instance of curl
     * @var resource $curl
     */
    private $curl;

    /**
     * Constructor
     *
     * @param string $consumerKey    OAuth consumer key
     * @param string $consumerSecret OAuth consumer secret
     * @param string $accessToken    OAuth access token
     * @param string $tokenSecret    OAuth token secret
     * @param bool   $debug          true to output debugging information to stdout
     */
    public function __construct($consumerKey, $consumerSecret, $accessToken, $tokenSecret, $debug = false)
    {
        // debug flag
        $this->debug = $debug;

        // oauth setup
        $this->oauth = new \OAuth($consumerKey, $consumerSecret);
        $this->oauth->setToken($accessToken, $tokenSecret);

        // curl setup
        $this->curl = curl_init();
        if (!$this->curl) {
            throw new \Exception("Failed to initialize curl");
        }

        // ca bundle
//        echo __DIR__;
//        if (!is_file(__DIR__ . '/ca.crt')) {
//            throw new \Exception("Failed to load ca certificate");
//        }
//        curl_setopt($this->curl, CURLOPT_CAINFO, __DIR__ . '/ca.crt');
    }

    /**
     * Send a piece of data
     *
     * @param  string $data    binary data
     * @param  int    $shareId setting this to zero is best, unless share id is known
     *
     * @return array  contains fingerprint and size, to be used when creating a file
     */
    public function sendData($data, $shareId = 0)
    {
        // first generate a part hash
        $fingerprint = $this->fingerprint($data);
        $part_size = strlen($data);

        // see if the cloud has this part, and send if needed
        if(!$this->hasPart($fingerprint, $part_size, $shareId))
            $this->sendPart($fingerprint, $part_size, $data, $shareId);

        // return information about this part
        return array("fingerprint" => $fingerprint, "size" => $part_size);
    }

    /**
     * Create a file with a set of data parts
     *
     * @param string $path  full path containing leading slash and file name
     * @param array  $parts contains arrays of parts returned by \Barracuda\Copy\API\sendData
     *
     * @return boolean True if the file was created successfully.
     */
    public function createFile($path, $parts)
    {
        if ($this->debug) {
            print("Creating file at path " . $path . "\n");
        }

        $request = array();
        $request["action"] = "create";
        $request["object_type"] = "file";
        $request["parts"] = array();
        $request["path"] = $path;

        $offset = 0;
        foreach ($parts as $part) {
            $partRequest["fingerprint"] = $part["fingerprint"];
            $partRequest["offset"] = $offset;
            $partRequest["size"] = $part["size"];

            array_push($request["parts"], $partRequest);

            $offset += $part["size"];
        }

        $request["size"] = $offset;

        $result = $this->post("update_objects", $this->encodeRequest("update_objects", array("meta" => array($request))));

        // Decode the json reply
        $result = json_decode($result);

        // Check for errors
        if ($result->{"error"} != null) {
            throw new \Exception("Error creating file '" . $result->{"error"}->{"message"} . "'");
        }

        return true;
    }

    /**
     * Send a request to remove a given file.
     *
     * @param string $path full path containing leading slash and file name
     *
     * @return bool True if the file was removed successfully
     */
    public function removeFile($path)
    {
        if ($this->debug) {
            print("Removing file at path " . $path . "\n");
        }

        $request = array();
        $request["action"] = "remove";
        $request["object_type"] = "file";
        $request["path"] = $path;

        $result = $this->post("update_objects", $this->encodeRequest("update_objects", array("meta" => array($request))));

        // Decode the json reply
        $result = json_decode($result);

        // Check for errors
        if ($result->{"error"} != null) {
            throw new \Exception("Error removing file '" . $result->{"error"}->{"message"} . "'");
        }

        return true;
    }

    /**
     * List objects within a path
     *
     * Object structure:
     * {
     *  object_id: "4008"
     *  path: "/example"
     *  type: "dir" || "file"
     *  share_id: "0"
     *  share_owner: "21956799"
     *  company_id: NULL
     *  size: filesize in bytes, 0 for folders
     *  created_time: unix timestamp, e.g. "1389731126"
     *  modified_time: unix timestamp, e.g. "1389731126"
     *  date_last_synced: unix timestamp, e.g. "1389731126"
     *  removed_time: unix timestamp, e.g. "1389731126" or empty string for non-deleted files/folders
     *  mime_type: string
     *  revisions: array of revision objects
     * }
     *
     * @param  string $path              full path with leading slash and optionally a filename
     * @param  array  $additionalOptions used for passing options such as include_parts
     *
     * @return array List of file/folder objects described above.
     */
    public function listPath($path, $additionalOptions = null)
    {
        $list_watermark = false;
        $return = array();

        do {
            $request = array();
            $request["path"] = $path;
            $request["max_items"] = 100;
            $request["list_watermark"] = $list_watermark;

            if ($additionalOptions) {
                $request = array_merge($request, $additionalOptions);
            }

            $result = $this->post("list_objects", $this->encodeRequest("list_objects", $request));

            // Decode the json reply
            $result = json_decode($result);

            // Check for errors
            if ($result->{"error"} != null) {
                throw new \Exception("Error listing path " . $path . ": '" . $result->{"error"}->{"message"} . "'");
            }

            // add the children if we got some, otherwise add the root object itself to the return
            if ($result->{"result"}->{"children"}) {
                $return = array_merge($return, $result->result->children);
                $list_watermark = $result->result->list_watermark;
            } else {
                $return[] = $result->result->object;
            }
        } while (isset($result->result->more_items) && $result->result->more_items == 1);

        return $return;
    }

    /**
     * Generate the fingerprint for a string of data.
     *
     * @param string $data Data part to generate the fingerprint for.
     *
     * @return string Fingerprint for $data.
     **/
    public function fingerprint($data)
    {
        return md5($data) . sha1($data);
    }

    /**
     *
     * Returns the binary header for a given data size and part count
     *
     * @param integer $size Byte count of the data being sent including part header info.
     * @param integer $part_count Number of parts being sent.
     *
     * @return string Binary header for a sendData request.
     **/
    public function packHeader($size, $part_count = 1)
    {
        $header =
            pack("N", self::HEADER_DELIMTER) .     // uint32_t Fixed signature "0xba5eba11"
            pack("N", self::HEADER_STRUCT_SIZE) .          // uint32_t Size of this structure
            pack("N", 1) .              // uint32_t Struct version (1)
            pack("N", $size) .          // uint32_t Total size of all data after the header
            pack("N", $part_count) .    // uint32_t Part count
            pack("N", 0);               // uint32_t Error code for errors regarding the entire request

        return $header;
    }

    /**
     *
     * Adds binary part information to the beginning of the data.
     *
     * @param string $fingerprint Fingerprint for the data being requested/sent.
     * @param string $part_size
     * @param string $data (optional) Data part to be packaged. Empty string for requests that send no data.
     * @param integer $shareId
     *
     * @return string Binary string with the header information and data.
     **/
    public function packPart($fingerprint, $part_size, $data = "", $shareId = 0)
    {
        $payload_size = strlen($data);

        // Pack in the part
        $partHeader =
            pack("N", self::PART_DELIMITER) .                           // uint32_t // "0xcab005e5"
            pack("N", self::PART_HEADER_STRUCT_SIZE  + $payload_size) . // uint32_t // Size of this struct plus payload size
            pack("N", 1) .                                              // uint32_t // Struct version
            pack("N", $shareId) .                                       // uint32_t // Share id for part (for verification)
            pack("a73", $fingerprint) .                                 // char[73] // Part fingerprint
            pack("N", $part_size) .                                     // uint32_t // Size of the part
            pack("N", $payload_size) .                                  // uint32_t // Size of our payload (partSize or 0, error msg size on error)
            pack("N", 0) .                                              // uint32_t // Error code for individual parts
            pack("N", 0);                                               // uint32_t // Reserved for future use

        return $partHeader . $data;

    }

    /**
     *
     * Parse the packed header to an API request into a
     * associative array.
     *
     * @param string $curl_result The response from the cURL request
     *
     * @return array Parsed header
     *
     **/
    private function parseResponseHeader($curl_result)
    {
        $response_header = unpack(
        // Parse our the header
            "N1signature/" .            // uint32_t Fixed signature "0xba5eba11"
            "N1size/" .                 // uint32_t Size of this structure
            "N1version/" .              // uint32_t Struct version (1)
            "N1totalSize/" .            // uint32_t Total size of all data after the header
            "N1partCount/" .            // uint32_t Part count
            "N1errorCode/",             // uint32_t Error code for errors regarding the entire
            $curl_result);

        if (!$response_header) {
            throw new \Exception("Failed to parse binary part reply");
        }

        return $response_header;
    }


    /**
     *
     * Parse the packed response body to an API request into a
     * associative array.
     *
     * @param string $curl_result The response from the cURL request
     *
     * @return array Parsed body
     *
     **/
    private function parseResponsePart($curl_result)
    {
        $part = unpack(
        // Parse out the part
            "N1partSignature/" .        // uint32_t // "0xcab005e5"
            "N1partWithPayloadSize/" .  // uint32_t // Size of this struct plus payload size
            "N1partVersion/" .          // uint32_t // Struct version
            "N1partShareId/" .          // uint32_t // Share id for part (for verification)
            "a73partFingerprint/" .     // char[73] // Part fingerprint
            "N1partSize/" .             // uint32_t // Size of the part
            "N1payloadSize/" .          // uint32_t // Size of our payload (partSize or 0, error msg size on error)
            "N1partErrorCode/" .        // uint32_t // Error code for individual parts
            "N1reserved/",              // uint32_t // Reserved for future use
            substr($curl_result, self::HEADER_STRUCT_SIZE));

        if (!$part) {
            throw new \Exception("Failed to parse binary part reply");
        }

        return $part;
    }

    /**
     * Send a data part
     *
     * @param string $fingerprint md5 and sha1 concatenated
     * @param int    $size        number of bytes
     * @param string $data        binary data
     * @param int    $shareId     setting this to zero is best, unless share id is known
     *
     * @return array Returns the unpacked response from the server separated into header and body components
     */
    public function sendPart($fingerprint, $size, $data, $shareId = 0)
    {
        // They must match
        if (md5($data) . sha1($data) != $fingerprint) {
            throw new \Exception("Failed to validate part hash");
        }

        if ($this->debug) {
            print("Sending part $fingerprint \n");
        }

        $packed_data = $this->packPart($this->fingerprint($data), strlen($data), $data, $shareId);

        $header = $this->packHeader(strlen($packed_data));

        if ($this->debug) {
            printf("Size of part request is " . strlen($packed_data) . "\n");
        }

        $result = $this->post("send_object_parts", $header . $packed_data);

        $response_header = $this->parseResponseHeader($result);

        // See if we got an error
        if ($response_header["errorCode"]) {
            // Just the error string remains
            throw new \Exception("Cloud returned part error " . "'" . substr($result, self::HEADER_STRUCT_SIZE) . "'");
        }

        $part = $this->parseResponsePart($result);

        // Check for part error
        if ($part["partErrorCode"]) {
            if ($this->debug) {
                var_dump($part);
            }
            throw new \Exception("Got part error " . $part["partErrorCode"] . "'" . substr($result, self::HEADER_STRUCT_SIZE + self::PART_HEADER_STRUCT_SIZE) . "'");
        }

        return array( "header" => $header, "body" => $part );
    }

    /**
     * Check to see if a part already exists
     *
     * @param  string $fingerprint md5 and sha1 concatinated
     * @param  int    $size        number of bytes
     * @param  int    $shareId     setting this to zero is best, unless share id is known
     * @return bool   true if part already exists
     */
    public function hasPart($fingerprint, $size, $shareId = 0)
    {
        if ($this->debug) {
            print("Checking if cloud has part $fingerprint \n");
        }

        $part = $this->packPart($fingerprint, $size, "", $shareId);

        $header = $this->packHeader(strlen($part));

        $result = $this->post("has_object_parts", $header . $part);

        $response_header = $this->parseResponseHeader($result);

        // See if we got an erro
        if ($response_header["errorCode"]) {
            // Just the error string remains
            throw new \Exception("Cloud returned error " . "'" . substr($result, self::HEADER_STRUCT_SIZE) . "'");
        }

        $response_body = $this->parseResponsePart($result);

        // Check for part error
        if ($response_body["partErrorCode"]) {
            throw new \Exception("Got part error " . $response_body["partErrorCode"] . "'" . substr($result, self::HEADER_STRUCT_SIZE + self::PART_HEADER_STRUCT_SIZE) . "'");
        }

        // Now the cloud will set the partSize field to zero if it doesn't have the part
        if ($part["partSize"] == 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get a part
     *
     * @param  string $fingerprint md5 and sha1 concatinated
     * @param  int    $size        number of bytes
     * @param  int    $shareId     setting this to zero is best, unless share id is known
     *
     * @return string binary data
     */
    public function getPart($fingerprint, $size, $shareId = 0)
    {
        if ($this->debug) {
            print("Getting part $fingerprint \n");
        }

        $part = $this->packPart($fingerprint, $size, "", $shareId);
        $header = $this->packHeader(strlen($part));

        $result = $this->post("get_object_parts", $header . $part);

        $response_header = $this->parseResponseHeader($result);

        // See if we got an erro
        if ($response_header["errorCode"]) {
            // Just the error string remains
            throw new \Exception("Cloud returned error " . "'" . substr($result, self::HEADER_STRUCT_SIZE) . "'");
        }

        $response_body = $this->parseResponsePart($result);

        // Check for part error
        if ($response_body["partErrorCode"]) {
            throw new \Exception("Got part error " . $response_body["partErrorCode"] . "'" . substr($result, self::HEADER_STRUCT_SIZE + self::PART_HEADER_STRUCT_SIZE) . "'");
        }

        // No error, see if data is in there
        if ($response_body["payloadSize"] == 0) {
            throw new \Exception("No data sent for part ");
        }

        // Get the data out of there
        $data = substr($result, self::HEADER_STRUCT_SIZE + self::PART_HEADER_STRUCT_SIZE, $response_body["payloadSize"]);

        // Triple check the data matches the fingerprint
        if ($this->fingerprint($data) != $fingerprint) {
            throw new \Exception("Failed to validate part hash");
        }

        // Part hash matches, return it
        return $data;
    }

    /**
     * Create and execute cURL request to send data.
     *
     * @param  string $method API method
     * @param  string $data   raw request
     *
     * @return mixed  result from curl_exec
     */
    protected function post($method, $data)
    {
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->getHeaders($method));
        curl_setopt($this->curl, CURLOPT_URL, $this->api_url . "/" . $this->getEndpoint($method));
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_POST, 1);

        $result = curl_exec($this->curl);

        // If curl grossly failed, throw
        if ($result == FALSE) {
            throw new \Exception("Curl failed to exec " . curl_error($this->curl));
        }

        return $result;
    }

    /**
     * Return which cloud API end point to use for a given method.
     *
     * @param  string $method API method
     *
     * @return string uri of endpoint without leading slash
     */
    private function getEndpoint($method)
    {
        if ($method == "has_object_parts" || $method == "send_object_parts" || $method == "get_object_parts") {
            return $method;
        } else {
            return "jsonrpc";
        }
    }

    /**
     * Generate the HTTP headers need for a given Cloud API method.
     *
     * @param  string $method API method
     *
     * @return array  contains headers to use for HTTP requests
     */
    private function getHeaders($method)
    {
        $headers = array();
        $endpoint = "jsonrpc";

        if ($method == "has_object_parts" || $method == "send_object_parts" || $method == "get_object_parts") {
            array_push($headers, "Content-Type: application/octect-stream");
        }

        array_push($headers, "X-Api-Version: 1.0");
        array_push($headers, "X-Client-Type: api");
        array_push($headers, "X-Client-Time: " . time());
        array_push($headers, "Authorization: " .  $this->oauth->getRequestHeader('POST', $this->api_url . "/" . $this->GetEndpoint($method)));

        return $headers;
    }

    /**
     * JSON encode request data.
     *
     * @param  string $method Cloud API method
     * @param  array  $json   contains data to be encoded
     *
     * @return string JSON formatted request body
     */
    private function encodeRequest($method, $json)
    {
        $request["jsonrpc"] = "2.0";
        $request["id"] = "0";
        $request["method"] = $method;
        $request["params"] = $json;
        $request = str_replace('\\/', '/', json_encode($request));
        if ($this->debug) {
            print("Encoded request " . var_export($request) . "\n");
        }

        return $request;
    }
}
