<?php

require __DIR__ . '/vendor/autoload.php';
use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
    ->setHosts(['0.0.0.0:9200']) //підключення  до носта elasticsearch
    ->build();

if (isset($_GET['q'])) {

    $q = $_GET['q'];

    $indexParams['index'] = 'my18';
    $index = $client->indices()->exists($indexParams); // перевіряю чи немає такого уже створеного індекса  (в elasticsearch це як база даних в mysql)
    if (!$index) {
        // і якщо його немає то створюю

        try {
            $params = [
                'index' => 'my18', // назва індекса
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                        'analysis' => [
                            'filter' => [
                                'shingle' => [
                                    'type' => 'shingle',
                                ],
                                'russian_stemmer' => [ // тут створила додатковий фільтер для налаштування російського аналізатора
                                    'type' => 'stemmer',
                                    'language' => 'russian',
                                ],

                            ],
                            'char_filter' => [
                                'pre_negs' => [
                                    'type' => 'pattern_replace',
                                    'pattern' => '(\\w+)\\s+((?i:never|no|nothing|nowhere|noone|none|not|havent|hasnt|hadnt|cant|couldnt|shouldnt|wont|wouldnt|dont|doesnt|didnt|isnt|arent|aint))\\b',
                                    'replacement' => '~$1 $2',
                                ],
                                'post_negs' => [
                                    'type' => 'pattern_replace',
                                    'pattern' => '\\b((?i:never|no|nothing|nowhere|noone|none|not|havent|hasnt|hadnt|cant|couldnt|shouldnt|wont|wouldnt|dont|doesnt|didnt|isnt|arent|aint))\\s+(\\w+)',
                                    'replacement' => '$1 ~$2',
                                ],
                            ],

                            'analyzer' => [
                                'reuters' => [ // назва мого аналізатора, який я налаштувала
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => ['lowercase', 'stop', 'kstem', 'russian_stemmer'], // передаю сюди фільтер з російським аналізатором
                                ],
                            ],
                        ],
                    ],
                    'mappings' => [ // mappings це як створення полів і їх налаштувань
                        'properties' => [
                            'title' => [
                                'type' => 'text', // тип поля
                                'analyzer' => 'reuters', // акий аналізатор
                                'copy_to' => 'combined',
                                'fielddata' => true, //  ці налаштування були необхідня для того щоб можна було по цьому полю відсортувати, без цього видась помилки при спробі запустити 'sort'
                            ],
                            'body' => [
                                'type' => 'text',
                                'analyzer' => 'reuters',
                                'copy_to' => 'combined',
                            ],
                            'combined' => [
                                'type' => 'text',
                                'analyzer' => 'reuters',
                            ],
                            'topics' => [
                                'type' => 'keyword',
                            ],
                            'places' => [
                                'type' => 'keyword',
                            ],
                        ],
                    ],
                ],
            ];
            $client->indices()->create($params); // створюю індекс по заданим вище параметрам

        } catch (Exception $e) {
            echo 'Выброшено исключение: ', $e->getMessage(), "\n";
        }
    }

    try {
        $querys = $client->search([ // створюю і налаштовую параметри пошуку
            'index' => 'my18', // назва індексу
            'body' => [
                "query" => [
                    'bool' => [
                        'should' => [ // ще є параметер must який повертатиме лише строго відповідні до запиту пощуку данні, should  більш гнучкий
                            "match" => [
                                "title" => [
                                    "query" => $q, // тут слово по якому ми шукажмо
                                    "fuzziness" => 2, // налаштування кількості можливих опечаток і помилок в слові (посимвольно)
                                    "operator" => "and", // можна передати ще 'or' теж для гнучності пошуку
                                ],
                            ],

                        ],
                    ],

                ],
                'sort' => [
                    'title' => [
                        'order' => 'asc', // сортування по полю title  по спаданню
                    ],
                ],
            ],

        ]);
    } catch (Exception $e) {
        echo 'Выброшено исключение1: ', $e->getMessage(), "\n";
    }

    if ($querys['hits']['total'] >= 1) {
        // якщо в запиті по пошуку є співпадіння по типу (аналог таблиці)

        $results = $querys['hits']['hits']; // отримуємо всі співпадіння з таблиці

        $resultNames = array_map(function ($item) {
            return $item['_source'];
        }, $querys['hits']['hits']);

    }
}

?>

<!-- HTML STARTS HERE -->
<!DOCTYPE>
<html>
    <head>
        <meta charset="utf-8">
        <title>Search Elasticsearch</title>
        <link rel="stylesheet" href="css/main.css">
    </head>
    <body>
        <form action="index.php" method="get" autocomplete="off">
            <label>
                Search for Something
                <input type="text" name="q">
            </label>
            <input type="submit" value="search">
        </form>

        <div class="res">
           <a href="#id">Attributes:  <?php foreach ($results as $res) {
    print_r($res['_source']['title'] . ' ');

}?>

           </a>
        </div>
        <div class="res">Name:  <?php foreach ($results as $res) {
    print_r($res['_source']['name'] . ' ');
}?></div>
<h1>Search Results</h1>

<ul>
<?php foreach ($resultNames as $resultName): ?>
    <li><?php echo implode(' ', $resultName); ?></li>
<?php endforeach;?>
</ul>
    </body>
</html>

