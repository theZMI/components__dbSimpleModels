<?php

namespace Models;

/**
 * Optimized base model
 *
 * @author  Zmi
 * @updated GYL
 */
abstract class ModelOptimized implements IModel
{
    protected $table;
    protected $db;
    private   $idField;
    private   $id;
    private   $data      = [];
    private   $dataStart = [];
    private   $readOnly  = false;
    private   $isDel     = false;

    abstract protected function createTable();

    private static $_queries           = [];
    private static $_listCreatedTables = [];

    public function __construct($db, $table, $idField, $id = null, $readOnly = false)
    {
        $this->db       = $db;
        $this->table    = $table;
        $this->idField  = $idField;
        $this->id       = $id;
        $this->readOnly = $readOnly;

        if (!$readOnly && !isset(self::$_listCreatedTables[$this->table])) {
            $this->createTable();
            self::$_listCreatedTables[$this->table] = true;
        }

        if ($this->id) {
            if (!isset(self::$_queries[$this->table][intval($this->id)])) {
                self::$_queries[$this->table][intval($this->id)] = ['data' => []];
            }

            $this->data = $this->dataStart = null;
        }
    }

    private function _realFillData()
    {
        if ($this->id && empty($this->data)) {
            if (empty(self::$_queries[$this->table][intval($this->id)]['data'])) {
                $ids = [];

                foreach (self::$_queries[$this->table] as $id => $v) {
                    if (empty($v['data'])) {
                        $ids[$id] = $id;
                    }
                }
                if (!empty($ids)) {
                    $list = $this->db->select(
                        "SELECT * FROM ?# WHERE ?# IN (?a)",
                        $this->table,
                        $this->idField,
                        $ids
                    );
                    foreach ($list as $v) {
                        $id                                                = $v[$this->idField];
                        self::$_queries[$this->table][intval($id)]['data'] = $v;
                    }
                }
            }

            $this->data = $this->dataStart = self::$_queries[$this->table][intval($this->id)]['data'];
        }
    }

    public function __set($key, $value)
    {
        if ($this->isDel) {
            trigger_error("Can not change removed object!", E_USER_ERROR);
        }

        if ($this->readOnly) {
            trigger_error("Can not change readonly object!", E_USER_ERROR);
        }

        if ($key == $this->idField || $key == 'id') {
            trigger_error("Can not change id field!", E_USER_ERROR);
        }

        $this->_realFillData();

        return $this->data[$key] = $value;
    }

    public function __get($key)
    {
        // Нельзя получать данные из объекта который удалён
        if ($this->isDel) {
            trigger_error("Can not get value from removed object!", E_USER_ERROR);
        }

        $this->_realFillData();

        // Если спрашивают ключевое поле, но сейчас идёт только заполнение данных то пытаетмся сделать запись в БД и вернуть id который она скажет
        if (in_array($key, [$this->idField, 'id']) && !$this->id && count($this->data)) {
            return $this->flush();
        }

        return $key == 'id'
            ? ($this->data[$this->idField] ?? null)
            : ($this->data[$key] ?? null);
    }

    public function __isset($key)
    {
        // Нельзя получать данные из объекта который удалён
        if ($this->isDel) {
            trigger_error("Can not get value from removed object!", E_USER_ERROR);
        }

        $this->_realFillData();

        // Если спрашивают ключевое поле, но сейчас идёт только заполнение данных то пытаетмся сделать запись в БД и вернуть id который она скажет
        if (in_array($key, [$this->idField, 'id']) && !$this->id && count($this->data)) {
            return $this->flush();
        }

        return $key == 'id' ? isset($this->data[$this->idField]) : isset($this->data[$key]);
    }

    public function flush()
    {
        $ret = false;

        if ($this->readOnly) {
            return $this->id ? $this->id : false;
        }

        if ($this->isDel) {
            return $ret;
        }

        if (is_array($this->data) && count($this->data)) {
            if ($this->id) {
                if ($this->dataStart != $this->data) {
                    $data = [];
                    foreach ($this->data as $k => $v) {
                        if ($k == $this->idField) {
                            continue;
                        }
                        $data[$k] = $v;
                    }

                    $ret = $this->db->query("
                                                UPDATE
                                                    ?#
                                                SET
                                                    ?a
                                                WHERE
                                                    ?# = ?d
                                            ",
                        $this->table,
                        $data,
                        $this->idField,
                        $this->id
                    );

                    // Если данные обновились, то обновляем их и в кеше запросов
                    self::$_queries[$this->table][intval($this->id)] = ['data' => $this->data];
                }

                $ret = $this->id;
            } else {
                $ret = $this->db->query("
                                            INSERT INTO
                                                ?#(?#)
                                            VALUES
                                                (?a)
                                        ",
                    $this->table,
                    array_keys($this->data),
                    array_values($this->data)
                );

                $this->id                                        = $this->data[$this->idField] = $ret;
                self::$_queries[$this->table][intval($this->id)] = ['data' => $this->data];
            }
        }

        return $ret;
    }

    public function isExists()
    {
        if (is_null($this->id)) {
            return false;
        }

        static $existeds = [];
        $ret = true;
        if (!in_array($this->id, array_keys($existeds))) {
            $ret = $this->db->selectCell("SELECT COUNT(*) FROM ?# WHERE ?# = ?d", $this->table, $this->idField, $this->id) > 0;
            if ($ret) {
                $existeds[$this->id] = $ret;
            }
        }

        return $ret;
    }

    public function resetChanges()
    {
        $this->_realFillData();
        $this->data = $this->dataStart;
    }

    public function isOnlyShow()
    {
        return $this->readOnly;
    }

    public function isDeleted()
    {
        return $this->isDel;
    }

    public function delete()
    {
        $ret = false;
        if ($this->readOnly || $this->isDel) {
            return $ret;
        }

        if ($this->id) {
            $ret = $this->db->query("DELETE FROM ?# WHERE ?# = ?d",
                $this->table,
                $this->idField,
                $this->id);
        }
        $this->isDel = $ret;

        return $ret;
    }

    public function __destruct()
    {
        return $this->flush();
    }

    public function getData()
    {
        $this->_realFillData();

        return $this->data;
    }

    public function hasChanges()
    {
        $this->_realFillData();

        return $this->data != $this->dataStart;
    }
}
