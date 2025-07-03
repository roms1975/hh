<?php

    require_once(__DIR__ . "/conf.php");
    require_once(__DIR__ . "/vendor/autoload.php");

    use GuzzleHttp\Client;

    define('TO_CSV', true);
    
    $_oauth_url = 'https://hh.ru/oauth/authorize';
    $_oauth_params = array(
        'client_id' => $_client_id,
        'redirect_uri' => $_redirect_url
    );

        
    $client = new Client();
    $a_token = get_token();
    $json_file = __DIR__ . "/vacancies.json";
    $csv_file = __DIR__ . "/vacancies.csv";
    $total = 0;
    $page = 0;
    $pages = 1;
    $vacancies = array();

    if (TO_CSV) {
        $fp = fopen($csv_file, 'w');
        $hrow = array(
            'Вакансия',
            'Дата размещения',
            'Город',
            'Компания',
            'Конт. телефоны',
            'Конт. email',
            'Контактное лицо',
            'Описание',
            'Зарплата',
        );

        fputcsv($fp, $hrow, ";");
    }

    while($page < $pages) {
        $res = get_vacations($page);
        $total = $res['found'];
        $pages = $res['pages'];
        $page++;

 
        foreach ($res['items'] as $row) {
            $vacancies[] = array(
                'name' => $row['name'],
                'city' => $row['area']['name'],
                'company' => $row['employer']['name'],
                'created' => date('Y-m-d H:i:s', strtotime($row['created_at'])),
                'contacts' => $row['contacts'],
                'salary' => $row['salary'],
            );

            if (TO_CSV) {
                $phones = implode(
                    ',',
                    array_map(
                        function($a) { 
                            print_r($a);
                            return $a['formatted'] ?: ''; 
                        }, 
                        ($row['contacts']['phones'] ?: [])
                    )
                );

//                echo $row[]'';
                $requirement = str_replace('</highlighttext>', '', ($row['snippet']['requirement'] ?: ''));
                $requirement = str_replace('<highlighttext>', '', $requirement);


                $r = array(
                    $row['name'],
                    date('Y-m-d H:i:s', strtotime($row['created_at'])),
                    $row['area']['name'],
                    $row['employer']['name'],
                    $phones,
                    ($row['contacts']['email'] ?: ''),
                    ($row['contacts']['name'] ?: ''),
                    $requirement,
                    ($row['salary']['from'] . (empty($row['salary']['to']) ? "" : ' - ' . $row['salary']['to']) . ' ' . $row['salary']['currency'])
                );
                fputcsv($fp, $r, ";");
            }
        }
        
        
    }

    echo "Total: {$total}\n";
//    print_r($res);
    file_put_contents($json_file, json_encode($vacancies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

function get_vacations($page = 0) {
    global $a_token;

    if (empty($a_token)) {
        error_log(__LINE__ . " Empty access token");
        exit();
    }

    $url = 'https://api.hh.ru/vacancies';
    $params = array(
        'text' => 'asterisk',
        'period' => 1,
        'page' => $page
//        'per_page' => 100
    );

    $headers = array(
        "Authorization: Bearer {$a_token}",
        "HH-User-Agent: Moshkin/1.0 (odggbo@gmail.com)",
        "Content-Type: application/json"
    );
    
    $curl = curl_init();
    $options = array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url . "?" . http_build_query($params),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers
    );

    curl_setopt_array($curl, $options);
    $result = curl_exec($curl);
    curl_close($curl);
    return json_decode($result, true);
}


function get_token($refresh = false) {
    $token_file = __DIR__ . "/token.json";
    if (!file_exists($token_file)) {
        error_log("Token file {$token_file} not found");
        exit();
    }

    try {
        $json = file_get_contents($token_file);
        $token = json_decode($json, true);
    } catch(Exception $e) {
        error_log("Wrong token. {$e}");
        exit();
    }

    return $refresh ? $token['refresh_token'] : $token['access_token'];
}

?>