<?php
class RequestHelper {
    //принимает ассоциативный массив и возвращает массив преобразованных значений
    final public static function get_Value($request)
    {
        $value = array();
        foreach ($request as $v) {
            if (is_string($v)) {
                $v = htmlspecialchars($v, ENT_NOQUOTES);
            }
            $value[] = $v;
        }
        return $value;
    }
    // принимает ассоциативный массив и возвращает массив знаков вопроса "(?)"
    final public static function get_Question_mark($count)
    {
        $question_mark = array_fill(0, $count, "(?)");
        return implode(", ", $question_mark);
    }
    //принимает ассоциативный массив и возвращает массив ключей, обрамленные обратными апострофами
    final public static function get_Property($request)
    {
        $property = [];
        foreach ($request as $k => $v) {
            $k = htmlspecialchars($k, ENT_NOQUOTES);
            $property[] = "`$k`";
        }
        return implode(", ", $property);
    }
}