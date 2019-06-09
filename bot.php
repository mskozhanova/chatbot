<?php
/**
 * Полезный чат-бот дл поиска по докам
 */

ini_set('error_reporting', E_ALL);

$appsConfig     = array();
$configFileName = '/config_' . trim(str_replace('.', '_', $_REQUEST['auth']['domain'])) . '.php';

if (file_exists(__DIR__ . $configFileName)) {
   include_once __DIR__ . $configFileName;
}
 
//include_once __DIR__ . "my_classes.php";

 //writeToLog($configFileName, 'ReportBot register'); 

//writeToLog($_REQUEST, '_REQUEST'); 
print_r($_REQUEST);


            $courses = [
               34 => Array("ID" => 34, "COLOR" => "#01b8ae", "CODE" => "content", "SAMPLE_SEARCH" => "Визуальный редактор", "NAME" => Array("ru" =>  "Контент-менеджер", "en" =>  "Content-manager" )),
               35 => Array("ID" => 35, "COLOR" => "rgb(1, 143, 188)", "CODE" => "admin_base", "SAMPLE_SEARCH" => "Свойства страницы", "NAME" => Array("ru" =>  "Администратор Базовый", "en" =>  "Admin Basic" )),
               43 => Array("ID" => 43, "COLOR" => "#4a4a4a", "CODE" => "fw", "SAMPLE_SEARCH" => "ORM D7", "NAME" =>  Array("ru" =>  "Разработчик Bitrix Framework", "en" =>  "Bitrix FW Developer" ) ),
            ];

            $commands = [
               'search_api_help' => Array(
                  "LANG" => Array(
                     Array('LANGUAGE_ID' => 'en', 'TITLE' => 'Doc Dev search', 'PARAMS' => 'your search'),
                     Array('LANGUAGE_ID' => 'ru', 'TITLE' => 'Документация для разработчиков', 'PARAMS' => 'Ваш запрос'),
                  ),
                  "SEARCH_URL" => "/api_help/",
                  "POST" => true,
                  "RESPONSE_TITLE" => 'Поиск по Документации для разработчиков',
                  "REGEXP" => '/href="(.+)">(\s+.+)menu-button">(\s.)(.+)<\/div>/mi',
                  "SEARCH_KEY" => "SearchQuery",
                  "COLOR" => "#6b737f", 
                  "SAMPLE_SEARCH" => "Специальные константы"
               ),
               'search_api_d7' => Array(
                  "LANG" => Array(
                     Array('LANGUAGE_ID' => 'en', 'TITLE' => 'Doc D7 search', 'PARAMS' => 'your search'),
                     Array('LANGUAGE_ID' => 'ru', 'TITLE' => 'Документация по D7', 'PARAMS' => 'Ваш запрос'),
                  ),
                  "SEARCH_URL" => "/api_d7/",
                  "POST" => true,
                  "RESPONSE_TITLE" => 'Поиск по Документации по D7',
                  "REGEXP" => '/href="(.+)">(\s+.+)menu-button">(\s.)(.+)<\/div>/mi',
                  "SEARCH_KEY" => "SearchQuery",
                  "COLOR" => "#6b737f", 
                  "SAMPLE_SEARCH" => "Datetime"
               ),               
            ];

            foreach($courses as $id => $item) {
               $commands['search_course_'.$item["CODE"]] =  Array(
                  "LANG" => Array(
                     Array('LANGUAGE_ID' => 'en', 'TITLE' => $item["NAME"]["en"], 'PARAMS' =>  'Your search'),
                     Array('LANGUAGE_ID' => 'ru', 'TITLE' => $item["NAME"]["ru"], 'PARAMS' => 'Ваш запрос' ),
                  ),
                  "SEARCH_URL" => "/learning/course/index.php",
                  "POST" => false,
                  "ADDITIONAL_PARAMS" => Array("COURSE_ID" => $id, "SEARCH" => "Y"),
                  "RESPONSE_TITLE" => 'Поиск по курсу ' . $item["NAME"]["ru"],
                  "REGEXP" => '/href="(.+)&sphrase_id=(\d+)">(.+)<\/a>/mi',
                  "SEARCH_KEY" => "q",
                  "COLOR" => $item["COLOR"],
                  "SAMPLE_SEARCH" => $item["SAMPLE_SEARCH"]
               );
            }

// receive event "new message for bot"
if ($_REQUEST['event'] == 'ONIMBOTMESSAGEADD') {
   // check the event - register this application or not
   if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) { 
      return false;
   }
   // response time

   $arReport = getAnswer($_REQUEST['data']['PARAMS']['MESSAGE'], $_REQUEST['data']['PARAMS']['FROM_USER_ID'], $commands);
 
 
   //writeToLog($arReport, 'arReport');

   foreach($arReport as $report) {
      // send answer message
      $result = restCommand('imbot.message.add', 
         array(
            "DIALOG_ID" => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
            "MESSAGE"   => $report['title'] ,
            "ATTACH"    => $report['attach'],
         ), 
         $_REQUEST["auth"]);      
   }


} // receive event "open private dialog with bot" or "join bot to group chat"
else {
   if ($_REQUEST['event'] == 'ONIMBOTJOINCHAT') {
      // check the event - register this application or not
      if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
         return false;
      }
      // send help message how to use chat-bot. For private chat and for group chat need send different instructions.

      $attach = getHelp($commands); 

      $result = restCommand('imbot.message.add', array(
         'DIALOG_ID' => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
         'MESSAGE'   => 'Привет! Я помощник в поиске документации и курсам Bitrix. Вы можете найти что угодно. Например, это команды, которые я понимаю',
         "ATTACH"    => $attach,
      ), $_REQUEST["auth"]);  
      $result = restCommand('imbot.message.add', array(
         'DIALOG_ID' => $_REQUEST['data']['PARAMS']['DIALOG_ID'],
         'MESSAGE'   => 'А еще я могу поведать Вам о состоянии Ваших незакрытых задач - вот так',
         "ATTACH"    => getHelp1(),
      ), $_REQUEST["auth"]);       

   } // receive event "delete chat-bot"
   else {
      if ($_REQUEST['event'] == 'ONIMBOTDELETE') {
         // check the event - register this application or not
         if (!isset($appsConfig[$_REQUEST['auth']['application_token']])) {
            return false;
         }
         // unset application variables
         unset($appsConfig[$_REQUEST['auth']['application_token']]);
         // save params
         saveParams($appsConfig);

      } // receive event "Application install"
      else {
         if ($_REQUEST['event'] == 'ONAPPINSTALL') {
            // handler for events
            $handlerBackUrl = ($_SERVER['SERVER_PORT']==443||$_SERVER["HTTPS"]=="on"||$_SERVER["HTTPS"] == true ? 'https' : 'http') . '://' . $_SERVER['SERVER_NAME'] . (in_array($_SERVER['SERVER_PORT'],
               array(80, 443)) ? '' : ':' . $_SERVER['SERVER_PORT']) . $_SERVER['SCRIPT_NAME'];
            // If your application supports different localizations
            // use $_REQUEST['data']['LANGUAGE_ID'] to load correct localization
            // register new bot
            $result = restCommand('imbot.register', array(
               'CODE'                  => 'DocBot',
               // строковой идентификатор бота, уникальный в рамках вашего приложения (обяз.)
               'TYPE'                  => 'B',
               // Тип бота, B - бот, ответы  поступают сразу, H - человек, ответы поступаю с задержкой от 2х до 10 секунд
               'EVENT_MESSAGE_ADD'     => $handlerBackUrl,
               // Ссылка на обработчик события отправки сообщения боту (обяз.)
               'EVENT_WELCOME_MESSAGE' => $handlerBackUrl,
               // Ссылка на обработчик события открытия диалога с ботом или приглашения его в групповой чат (обяз.)
               'EVENT_BOT_DELETE'      => $handlerBackUrl,
               // Ссылка на обработчик события удаление бота со стороны клиента (обяз.)
               'PROPERTIES'            => array( // Личные данные чат-бота (обяз.)
                  'NAME'              => 'М',
                  // Имя бота (обязательное одно из полей NAME или LAST_NAME)
                  'LAST_NAME'         => 'К',
                  // Фамилия бота (обязательное одно из полей NAME или LAST_NAME)
                  'COLOR'             => 'PURPLE',
                  // Цвет бота для мобильного приложения RED,  GREEN, MINT, LIGHT_BLUE, DARK_BLUE, PURPLE, AQUA, PINK, LIME, BROWN,  AZURE, KHAKI, SAND, MARENGO, GRAY, GRAPHITE
                  'EMAIL'             => 'ms.kozhanova@gmail.com',
                  // Емейл для связи
                  'PERSONAL_BIRTHDAY' => '1980-01-07',
                  // День рождения в формате YYYY-mm-dd
                  'WORK_POSITION'     => 'Маленький помощник 1C-Bitrix',
                  // Занимаемая должность, используется как описание бота
                  'PERSONAL_WWW'      => 'http://dev.1c-bitrix.ru',
                  // Ссылка на сайт
                  'PERSONAL_GENDER'   => 'F',
                  // Пол бота, допустимые значения M -  мужской, F - женский, пусто если не требуется указывать
                  'PERSONAL_PHOTO' => base64_encode(file_get_contents(__DIR__.'/bitrix_doc.png')),
                  // Аватар бота - base64
               ),
            ), $_REQUEST["auth"]);

            $botId = $result['result'];


            $commandIDs = [];

            foreach($commands as $id => $command) {
            


               $result = restCommand('imbot.command.register', Array(
                  'BOT_ID' => $botId,
                  'COMMAND' => $id,
                  'COMMON' => 'Y',
                  'HIDDEN' => 'N',
                  'EXTRANET_SUPPORT' => 'N',
                  'LANG' => $command["LANG"],
                  'EVENT_COMMAND_ADD' => $handlerBackUrl,
               ), $_REQUEST["auth"]);
               
               $commandIDs[] = $result['result']; 
                         
            }
            //writeToLog($commandIDs, 'install commands');    


            // save params
            $appsConfig[$_REQUEST['auth']['application_token']] = array(
               'BOT_ID'      => $result['result'],
               'COMMANDS_IDS' => implode(",", $commandIDs),
               'LANGUAGE_ID' => $_REQUEST['data']['LANGUAGE_ID'],
            );
            saveParams($appsConfig);
            // write debug log
             writeToLog($result, 'ReportBot register');
         } else {
            if ($_REQUEST['event'] == 'ONIMCOMMANDADD') {
               // check the event - authorize this event or not
               if (!isset($appsConfig[$_REQUEST['auth']['application_token']]))
                  return false;


               $result = false;     

               foreach ($_REQUEST['data']['COMMAND'] as $command) {

                  $answer =  getResults($command, $commands);


                  $result = restCommand('imbot.command.answer', Array(
                     "COMMAND_ID" => $command['COMMAND_ID'],
                     "MESSAGE_ID" => $command['MESSAGE_ID'],
                     "MESSAGE" => $answer["MESSAGE"],
                     "ATTACH" => $answer["ATTACH"],
                     /*"MENU" => Array(
                        Array("TEXT"=>"Перейти на ресурс", "LINK" => $commands[$command["COMMAND"]]["SEARCH_URL"])
                     )*/
                  ), $_REQUEST["auth"]);

                  //writeToLog($result, 'command answer');

               }          
            }
         }
      }
   }
}
/**
 * Save application configuration.
 *
 * @param $params
 *
 * @return bool
 */
function saveParams($params) {
   $config = "<?php\n";
   $config .= "\$appsConfig = " . var_export($params, true) . ";\n";
   $config .= "?>";
   $configFileName = '/config_' . trim(str_replace('.', '_', $_REQUEST['auth']['domain'])) . '.php';
   file_put_contents(__DIR__ . $configFileName, $config);
   return true;
}
/**
 * Send rest query to Bitrix24.
 *
 * @param       $method - Rest method, ex: methods
 * @param array $params - Method params, ex: array()
 * @param array $auth   - Authorize data, ex: array('domain' => 'https://test.bitrix24.com', 'access_token' => '7inpwszbuu8vnwr5jmabqa467rqur7u6')
 *
 * @return mixed
 */
function restCommand($method, array $params = array(), array $auth = array()) {
   $queryUrl  = 'https://' . $auth['domain'] . '/rest/' . $method;
   $queryData = http_build_query(array_merge($params, array('auth' => $auth['access_token'])));
   // writeToLog(array('URL' => $queryUrl, 'PARAMS' => array_merge($params, array("auth" => $auth["access_token"]))), 'ReportBot send data');
   $curl = curl_init();
   curl_setopt_array($curl, array(
      CURLOPT_POST           => 1,
      CURLOPT_HEADER         => 0,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL            => $queryUrl,
      CURLOPT_POSTFIELDS     => $queryData,
   ));
   $result = curl_exec($curl);
   curl_close($curl);
   $result = json_decode($result, 1);
   return $result;
}
/**
 * Write data to log file.
 *
 * @param mixed  $data
 * @param string $title
 *
 * @return bool
 */
function writeToLog($data, $title = '') {
   $log = "\n------------------------\n";
   $log .= date("Y.m.d G:i:s") . "\n";
   $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
   $log .= print_r($data, 1);
   $log .= "\n------------------------\n";
   file_put_contents(__DIR__ . '/imbot.txt', $log, FILE_APPEND);
   return true;
}


function getHelp($commands) {
   $attach = array();

         foreach($commands as $id => $command) {
         $attach[] = array(
            'MESSAGE' => '[send=/'.$id.' '.$command["SAMPLE_SEARCH"].'] ' .$command["RESPONSE_TITLE"].': '.$command["SAMPLE_SEARCH"].'[/send]'
         );
      }



   return $attach;
}

function getHelp1() {
    return array(array('MESSAGE' => '[send=мои задачи]мои задачи[/send]')); 

}
 
/**
 * Формируем отчет по поиску
 *
  
 */
function getResults($command, $commands) {
   
   //writeToLog($commands[$command["COMMAND"]], 'answer 111'); 
      /*поищем в доке*/
      $answer = docSearch(
         $command["COMMAND_PARAMS"], 
         $commands[$command["COMMAND"]]["SEARCH_KEY"],
         $commands[$command["COMMAND"]]["SEARCH_URL"], 
         $commands[$command["COMMAND"]]["ADDITIONAL_PARAMS"], 
         $commands[$command["COMMAND"]]["REGEXP"],
         $commands[$command["COMMAND"]]["POST"]
      ); 

      
      $answer["MESSAGE"] = "[B]".$commands[$command["COMMAND"]]["RESPONSE_TITLE"]."[/B][BR]результаты поиска по запросу [i]".$command["COMMAND_PARAMS"]."[/i]";

      if($commands[$command["COMMAND"]]["COLOR"])
         $res["ATTACH"]["COLOR"] = $commands[$command["COMMAND"]]["COLOR"];

 

   //writeToLog($answer, 'answer 111');

   return $answer;
}

function docSearch($SearchQuery, $SearchKey, $queryPage, $additionalParams, $regExpression, $isPost) {
   if($additionalParams == false)
      $additionalParams = array();

   if(!$regExpression)
      return [];

   $queryDomain  = 'https://dev.1c-bitrix.ru';
   //$queryPage = '/api_help/index.php' ;
   $queryUrl = $queryDomain . $queryPage;
   $queryData = http_build_query(array_merge($additionalParams, array($SearchKey => $SearchQuery)));
   $curl = curl_init();
   curl_setopt_array($curl, array(
      CURLOPT_POST           => $isPost ? 1 : 0,
      CURLOPT_HEADER         => 0,
      CURLOPT_RETURNTRANSFER => 1,      
      CURLOPT_URL            => $queryUrl,
      CURLOPT_POSTFIELDS     => $queryData,
   ));
   $result = curl_exec($curl);
   curl_close($curl);
   //writeToLog(array($SearchQuery, $queryPage, $additionalParams, $isPost), 'data logg ');
   //writeToLog($result, 'result result');

   //$regExpression = '/href="(.+)">(\s+.+)menu-button">(\s.)(.+)(\s.)<\/div>/mi'; //регулярка поиска
   //writeToLog($regExpression, 'regExpression');
   $matches = [];
   //preg_match($regExpression, $result, $matches, PREG_OFFSET_CAPTURE);
   preg_match_all($regExpression, $result, $matches, PREG_SET_ORDER, 0);
   //writeToLog($matches, 'matches');
   $res = formatResult($matches, $queryDomain); 

   if(!$isPost && count($res["ATTACH"]["BLOCKS"]) > 0) {
      $res["ATTACH"]["BLOCKS"][] = Array("DELIMITER" => Array('SIZE' => 200, 'COLOR' => "#c6c6c6"));
      $res["ATTACH"]["BLOCKS"][] = Array("LINK" => Array(
         "NAME" => "Все результаты", 
         "LINK" => $queryUrl."?".$queryData
      ));
   }

   if($res["ATTACH"]["BLOCKS"] == 0){
         $res["ATTACH"]["BLOCKS"][] = Array(
            "MESSAGE" => "Ничего не найдено"
         );       
   }
   
   return $res;
}

function formatResult($matches, $queryDomain) {
   $res = Array("MESSAGE" => "Извините, ничего не найдено", "ATTACH" => array());

   if(is_array($matches) && count($matches) > 0) {
      $res["MESSAGE"] = "Результаты поиска"; 
      $res["ATTACH"] = Array(  "BLOCKS" => array());


      foreach($matches as $m) {
         //writeToLog(implode(", ", $m), 'implode m');
         $href = $m[1];
         $pos = trim($m[count($m)-1]);



         $res["ATTACH"]["BLOCKS"][] = Array(
            "LINK" => Array("NAME" =>  str_replace(array('<b>', '</b>'), array('', ''), $pos), "LINK" => $queryDomain.$href )
         );  

         

      }      
   }

   //writeToLog($res, 'res');
   
   return $res;   
}





/**
 * Формируем отчет по команде
 *
 * @param      string $text строка, которую отправил юзер
  * @param      int $user идентификатор пользователя, который нам написал
 *
 * @return     array
 */
function getAnswer($command = '', $user, $commands) {

   switch (strtolower($command)) {
       case 'мои задачи':
           $arResult = myTasks($user);
           break;
       default:
          $arResult = array(
            array(
               'title' => '"Доктор, моя собака неадекватно реагирует на комады!"',
               'attach' => Array(
                  Array("IMAGE" => Array(                
                       "LINK" =>  "https://dia-box.ru/apps/bot/dog.png",
                       "PREVIEW" => "https://dia-box.ru/apps/bot/dog.png",
                       "WIDTH" => "500",
                       "HEIGHT" => "400"
                   )),
                  Array("LINK" => Array(
                     "NAME" => "Ералаш №103 на youtube",
                     "LINK" =>  "https://www.youtube.com/watch?v=DQTrN1mc1NY",
                  ))
               )              
            ),
            array(
               'title'  => 'Не соображу, что вы хотите узнать. А может вообще не умею... Со мной лучше общаться примерно так: ',
               'attach' => getHelp($commands)
            ),
            array(
               'title'  => 'Или так: ',
               'attach' => getHelp1()
            )
            
            

         );
   }

   return $arResult;
}

function myTasks($user) {
   $tasks = restCommand('tasks.task.list', 
      array(
         'order' => array('DEADLINE' => 'desc'),
         'filter' => array('RESPONSIBLE_ID' => $user, '!REAL_STATUS' => 5),
         'select' => array('ID', 'TITLE', 'DEADLINE', 'RESPONSIBLE_ID', 'CLOSED_DATE', 'STATUS')
      ), 
      $_REQUEST["auth"]);      

   //writeToLog($tasks, 'myTasks'); 
   $d = new Datetime();
   $dateISOstring = $d->format(DateTimeInterface::ISO8601);

   //writeToLog($dateISOstring, 'my date dateISOstring'); 

   $res = array();

   if($tasks["error"]) {
      $res[] = showError($tasks["error"], $tasks["error_description"]);
   } 

   if($tasks["result"]) {
      if(is_array($tasks["result"]["tasks"]) && count($tasks["result"]["tasks"]) > 0) {
         foreach($tasks["result"]["tasks"] as $task) {
            $deadlineDate = DateTime::createFromFormat(DateTimeInterface::ISO8601, $task["deadline"]);
            $res[] = array(
               'title' => $task["title"] . ( $task["deadline"] < $dateISOstring ? ' [b]просрочено[/b]' : ''),
               'attach' => array(
                  'COLOR' => $task["deadline"] < $dateISOstring ? '#ff0000' : '#000099',
                  'BLOCKS' => array(
                     Array("MESSAGE" => "Дедлайн задачи: " . $deadlineDate->format('Y-m-d H:i:s')),
                     Array(
                        "LINK" => array(
                           'NAME' => 'Перейти к задаче', 
                           'LINK' => 'https://'.$_REQUEST['auth']['domain'].'/company/personal/user/'.$task['responsibleId'].'/tasks/task/view/'.$task['id'].'/')
                     )
                  )
               )
            );
         }         
      }

   }



   return $res;
}

function showError($e_code, $e_descr) {
   return array(
      "title" => '[' . $e_code . ']',
      "attach" => array(array(
            'MESSAGE' => $e_descr
         ))
   );
}
