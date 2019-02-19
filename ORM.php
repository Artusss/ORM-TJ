<?php
/** @noinspection ALL */
require_once 'includes/Bean.php';
require_once 'includes/RequestHelper.php';

class Singleton //реализация одноименного шаблона
{
    protected static $_instance = null;
    protected function __clone() {}
    protected function __wakeup() {}
}
interface iCRUD
{
    public function setBean($table_name, $bean_assoc);
    public function getBean($bean);
    public function save($bean);
    public function read($table_name, $request, $value_arr);
    public function readOne($table_name, $request, $value_arr, $what_select);
    public function readCount($table_name, $request, $value_arr);
    public function exists($table_name, $request, $value_arr);
    public function update($table_name, $request_set, $request_condition, $value_arr);
    public function delete($table_name, $request, $value_arr);
    public function clean($table_name);
    public function close();
}
interface iMerger
{
    public function join($type, $table_arr, $request, $value_arr, $what_select);
    public function union($table_name, $request, $value_arr, $what_select);
    public function unionAll($table_name, $request, $value_arr, $what_select);
}

class TJ extends Singleton implements iCRUD, iMerger
{
    protected static $db_name; //имя базы данных
    protected static $host; //имя хоста
    protected static $user_name; //пользователь
    protected static $password; //пароль
    protected static $charset; //кодировка
    protected static $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]; //параметры по умолчанию

    protected $connection; //хранит соединение с БД

    protected function __construct() { //вызывается методом Init()
        $host = self::$host;
        $db_name = self::$db_name;
        $charset = self::$charset;
        $dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
        $options = self::$options;
        $this->connection = new \PDO($dsn, self::$user_name, self::$password, $options);
    }

    //создает единственный обЪект класса TJ или его дочерних классов
    public static function Init($db_name, $host = 'localhost', $user_name = 'root', $password = '', $charset = 'utf8'){
        self::$host = $host;
        self::$user_name = $user_name;
        self::$password = $password;
        self::$db_name = $db_name;
        self::$charset = $charset;
        if(is_null(self::$_instance)){
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    //создает объектное представление строки в таблице
    public function setBean($table_name, $bean_assoc = []){
        $bean = new Bean($table_name);
        foreach ($bean_assoc as $k => $v){
            $bean->$k = $v;
        }
        return $bean;
    }

    //возвращает свойства объект класса Bean
    public function getBean($bean){
        return get_object_vars($bean);
    }

    //записывает объект Bean в БД и возвращает id данной записи из БД
    public function save($bean){
        try{
            $pdo = $this->connection;
            $bean_assoc = $this->getBean($bean);
            $table_name = array_shift($bean_assoc);
            $str_property = RequestHelper::get_Property($bean_assoc);
            $str_question_mark = RequestHelper::get_Question_mark(count($bean_assoc));
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $sql_request_field = "INSERT INTO {$table_name_field} ({$str_property}) VALUES ({$str_question_mark})";
            $stmt = $pdo->prepare($sql_request_field);
            $stmt->execute(RequestHelper::get_Value($bean_assoc));
            $last_insert_id = $pdo->lastInsertId();
            unset($stmt, $pdo);
            return $last_insert_id;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //читает из БД строки и возвращает их в виде ассоциативного массива
    public function read($table_name, $request = '1', $value_arr = null, $what_select='*')
    {
        try {
            $pdo = $this->connection;
            $table_content = array();
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_field = "SELECT {$what_select} FROM {$table_name_field} WHERE {$request}";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            while (($row = $stmt->fetch())) {
                $table_content[] = $row;
            }
            unset($stmt, $pdo);
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
        return $table_content;
    }

    //читает из БД строку и возвращает ее в виде объекта класса Bean
    public function readOne($table_name, $request = '1', $value_arr = null, $what_select='*')
    {
        try {
            $pdo = $this->connection;
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_field = "SELECT {$what_select} FROM {$table_name_field} WHERE {$request}";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            $table_content = $stmt->fetch();
            unset($stmt, $pdo);
            return $this->setBean($table_name, $table_content);
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //возвращает колличество строк
    public function readCount($table_name, $request = '1', $value_arr = null)
    {
        try {
            $pdo = $this->connection;
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_field = "SELECT COUNT(`id`) as `total_count` FROM {$table_name_field} WHERE {$request}";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            $count_result = $stmt->fetch()['total_count'];
            unset($stmt, $pdo);
            return $count_result;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //возвращает true если данная строка существует и false если нет
    public function exists($table_name, $request = '1', $value_arr = null)
    {
        try {
            $pdo = $this->connection;
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_field = "SELECT `id` FROM {$table_name_field} WHERE {$request}";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            if($stmt->fetch()) {
                $exists = true;
            }
            unset($stmt, $pdo);
            return isset($exists) ? $exists : false;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //обновляет строку в БД
    public function update($table_name, $request_set, $request_condition, $value_arr = null)
    {
        try {
            $pdo = $this->connection;
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_field = "UPDATE {$table_name_field} SET {$request_set} WHERE {$request_condition}";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute(RequestHelper::get_Value($value_arr));
            unset($stmt, $pdo);
            return true;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //удаляет строки из БД
    public function delete($table_name, $request, $value_arr = null)
    {
        try {
            $pdo = $this->connection;
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_field = "DELETE FROM {$table_name_field} WHERE {$request}";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            unset($stmt, $pdo);
            return true;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //очищает таблицу (не удаляет ее)
    public function clean($table_name){
        try {
            $pdo = $this->connection;
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_field = "TRUNCATE TABLE {$table_name_field}";
            $stmt = $pdo->query($request_field);
            unset($stmt, $pdo);
            return true;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //объединяет 2 таблицы в одну с возможностью задать общие параметры в виде разных имен
    public function join($type, $table_arr, $request = '1', $value_arr = null, $what_select='*')
    {
        try {
            $pdo = $this->connection;
            $two_tables_content = array();
            $name = array();
            $col = array();
            foreach($table_arr as $k => $v){
                $name[] = $k;
                $col[] = "{$k}.{$v}";
            }
            $request_field = "SELECT {$what_select} FROM {$name[0]} {$type} JOIN {$name[1]} ON {$col[0]} = {$col[1]} WHERE {$request}";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            while (($row = $stmt->fetch())) {
                $two_tables_content[] = $row;
            }
            unset($stmt, $pdo);
            return $two_tables_content;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //объединяет 2 набора строк 
    public function union($table_name, $request = ['1', '1'], $value_arr = null, $what_select=['*', '*']){
        try {
            $pdo = $this->connection;
            $table_content = array();
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_1 = "SELECT {$what_select[0]} FROM {$table_name_field} WHERE {$request[0]}";
            $request_2 = "SELECT {$what_select[1]} FROM {$table_name_field} WHERE {$request[1]}";
            $request_field = "({$request_1}) UNION ({$request_2})";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            while (($row = $stmt->fetch())) {
                $table_content[] = $row;
            }
            unset($stmt, $pdo);
            return $table_content;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //полностью объединяет 2 набора строк (совпадения не заменяются друг другом)
    public function unionAll($table_name, $request = ['1', '1'], $value_arr = null, $what_select=['*', '*']){
        try {
            $pdo = $this->connection;
            $table_content = array();
            $table_name_field = "`" . str_replace("`", "``", $table_name) . "`";
            $request_1 = "SELECT {$what_select[0]} FROM {$table_name_field} WHERE {$request[0]}";
            $request_2 = "SELECT {$what_select[1]} FROM {$table_name_field} WHERE {$request[1]}";
            $request_field = "({$request_1}) UNION ALL ({$request_2})";
            $stmt = $pdo->prepare($request_field);
            $stmt->execute($value_arr);
            while (($row = $stmt->fetch())) {
                $table_content[] = $row;
            }
            unset($stmt, $pdo);
            return $table_content;
        } catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //закрывает соединение с БД
    public function close($message=''){
        echo $message;
        unset($this->connection);
    }

    function __destruct()
    {
        unset($this->connection);
    }
}