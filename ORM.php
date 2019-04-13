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
        $this->connection = new \PDO($dsn, self::$user_name, self::$password, $options); //Производит соединение с БД
    }

    protected function execSelect($prepare, $exec){ //Выполняет подготовленный запрос при select
        try{
            $pdo = $this->connection;
            $stmt = $pdo->prepare($prepare); //Подготавливаем выражение
            $stmt->execute($exec); //Вставляем список параметров
            $fetch = $stmt->fetch(); //Получаем ассоциативный массив данных из БД
            unset($pdo, $stmt); //Заканчиваем запрос удаляя лишние переменные
            return $stmt->fetch(); //Возвращаем массив
        }catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    protected function execInsert($prepare, $exec){ //Аналогичен методу выше для insert
        try{
            $pdo = $this->connection;
            $stmt = $pdo->prepare($prepare);
            $stmt->execute($exec);
            $lastInsertId = $stmt->lastInsertId();
            unset($pdo, $stmt);
            return $lastInsertId; //Возвращаем id добавленной строки
        }catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    protected function execUpdate($prepare, $exec){ //Аналогичен методу выше для update и delete
        try{
            $pdo = $this->connection;
            $stmt = $pdo->prepare($prepare);
            $stmt->execute($exec);
            unset($pdo, $stmt);
        }catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    protected function query($query){ //Аналогичен методу выше для обычного запроса
        try{
            $pdo = $this->connection;
            $stmt = $pdo->query($query);
            unset($pdo, $stmt);
        }catch (PDOException $e) {
            die('Подключение не удалось: ' . $e->getMessage());
        }
    }

    //создает единственный обЪект класса TJ или его дочерних классов
    public static function Init($db_name, $host = 'localhost', $user_name = 'root', $password = '', $charset = 'utf8'){
        self::$host = $host;
        self::$user_name = $user_name;
        self::$password = $password;
        self::$db_name = $db_name;
        self::$charset = $charset;
        if(is_null(self::$_instance)){
            self::$_instance = new self; //Если отсутствует экземпляр класса, создаем его
        }
        return self::$_instance; //Возвращаем единственный экземпляр класса
    }

    //создает объектное представление строки в таблице
    public function setBean($table_name, $bean_assoc = []){
        $bean = new Bean($table_name); //создаем экземпляр класса Bean
        foreach ($bean_assoc as $k => $v){
            $bean->$k = $v; //С помощью динамических переменных создаем свойства по названию столбцов таблицы
        }
        return $bean;
    }

    //возвращает свойства объект класса Bean
    public function getBean($bean){
        return get_object_vars($bean);
    }

    //записывает объект Bean в БД и возвращает id данной записи из БД
    public function save($bean){
        $bean_assoc = $this->getBean($bean); //Получаем ассоциативный массив свойств класса Bean
        $table_name = array_shift($bean_assoc); //Удаляем из списка свойств название таблицы принадлежности
        $str_property = RequestHelper::get_Property($bean_assoc); //Возвращаем массив ключей
        $str_question_mark = RequestHelper::get_Question_mark(count($bean_assoc)); //Возвращаем знаки вопроса
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $sql_request_field = "INSERT INTO {$table_name} ({$str_property}) VALUES ({$str_question_mark})"; //Составляем запрос

        return $this->execInsert($sql_request_field, RequestHelper::get_Value($bean_assoc));
    }

    //читает из БД строки и возвращает их в виде ассоциативного массива
    public function read($table_name, $request = '1', $value_arr = null, $what_select='*')
    {
        $table_content = array();
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $request_field = "SELECT {$what_select} FROM {$table_name} WHERE {$request}"; //Составляем запрос

        $fetch = $this->execSelect($request_field, $value_arr);

        while (($row = $fetch)) { //Добавляем все записи из таблицы
            $table_content[] = $row;
        }

        return $table_content;
    }

    //читает из БД строку и возвращает ее в виде объекта класса Bean
    public function readOne($table_name, $request = '1', $value_arr = null, $what_select='*')
    {
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $request_field = "SELECT {$what_select} FROM {$table_name} WHERE {$request}"; //Составляем запрос

        $fetch = $this->execSelect($request_field, $value_arr);

        return $this->setBean($table_name, $fetch);
    }

    //возвращает колличество строк
    public function readCount($table_name, $request = '1', $value_arr = null)
    {
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $request_field = "SELECT COUNT(`id`) as `total_count` FROM {$table_name} WHERE {$request}"; //Составляем запрос

        $fetch = $this->execSelect($request_field, $value_arr);

        return $fetch['total_count']; //Возвращаем колличество записей
    }

    //возвращает true если данная строка существует и false если нет
    public function exists($table_name, $request = '1', $value_arr = null)
    {
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $request_field = "SELECT `id` FROM {$table_name} WHERE {$request}"; //Составляем запрос

        $fetch = $this->execSelect($request_field, $value_arr);

        if($fetch) {
            return true;
        }
        return false;
    }

    //обновляет строку в БД
    public function update($table_name, $request_set, $request_condition, $value_arr = null)
    {
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $request_field = "UPDATE {$table_name} SET {$request_set} WHERE {$request_condition}"; //Составляем запрос

        $this->exec($request_field, RequestHelper::get_Value($value_arr));

        return true;
    }

    //удаляет строки из БД
    public function delete($table_name, $request, $value_arr = null)
    {
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $request_field = "DELETE FROM {$table_name} WHERE {$request}"; //Составляем запрос

        $this->exec($request_field, $value_arr);

        return true;
    }

    //очищает таблицу (не удаляет ее)
    public function clean($table_name){
        $table_name = "`" . str_replace("`", "``", $table_name) . "`"; //Экранируем название
        $request_field = "TRUNCATE TABLE {$table_name}"; //Составляем запрос

        $this->query($request_field);

        return true;
    }

    //объединяет 2 таблицы в одну с возможностью задать общие параметры в виде разных имен
    public function join($type, $table_arr, $request = '1', $value_arr = null, $what_select='*')
    {
        $two_tables_content = array();
        $name               = array();
        $col                = array();
        foreach($table_arr as $k => $v){ //составляем шаблоны для вставки в запрос Join
            $k = "`" . str_replace("`", "``", $k) . "`"; //Экранируем название
            $name[] = $k;
            $col[] = "{$k}.{$v}";
        }
        $request_field = "SELECT {$what_select} FROM {$name[0]} {$type} JOIN {$name[1]} ON {$col[0]} = {$col[1]} WHERE {$request}"; //Составляем запрос
        $fetch = $this->execSelect($request_field, $value_arr);
        while (($row = $fetch)) { //Добавляем все записи из таблицы
            $two_tables_content[] = $row;
        }
        return $two_tables_content;
    }

    //закрывает соединение с БД
    public function close($message=''){
        echo $message; //Выводим сообщение (если оно есть) о закрытии соединения
        unset($this->connection); //Закрываем соединение
    }

    function __destruct()
    {
        unset($this->connection); //Закрываем соединение
    }
}