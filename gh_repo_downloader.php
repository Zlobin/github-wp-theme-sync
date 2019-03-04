<?php

class GHRepoDownloader {
    private function exec_redirects($curl, &$redirects) {
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($http_code == 301 || $http_code == 302) {
            list($header) = explode("\r\n\r\n", $data, 2);
            $matches = array();
            preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
            $url = trim(str_replace($matches[1], '', $matches[0]));
            $url_parsed = parse_url($url);
            if (isset($url_parsed)) {
                curl_setopt($ch, CURLOPT_URL, $url);
                $redirects++;
                return $this->exec_redirects($ch, $redirects, true);
            }
        }

        list(, $body) = explode("\r\n\r\n", $data, 2);
        return $body;
    }

    public function download($options) {
        extract($options);

        $curl = curl_init('https://api.github.com/repos/' . $repo . '/zipball/' . $branch);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: token ' . $token, 'User-Agent: GhRepoDownloader'));
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $data = $this->exec_redirects($curl, $out);

        curl_close($curl);

		$handler = fopen($saveAs, 'a+');
		fwrite($handler, $data);
		fclose($handler);
    }
}
