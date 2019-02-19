<?php
class Bean{
    public $table_name; //имя представления
    public function __construct($table_name)
    {
        $this->table_name = $table_name;
    }
}