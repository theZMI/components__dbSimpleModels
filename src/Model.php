<?php

namespace Models;

/**
 * Base model
 *
 * @author  Zmi
 * @updated GYL
 */
abstract class Model implements IModel
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

    public function __construct($db, $table, $idField, $id = null, $readOnly = false)
    {
        $this->db       = $db;
        $this->table    = $table;
        $this->idField  = $idField;
        $this->id       = $id;
        $this->readOnly = $readOnly;

        if (!$readOnly) {
            $this->createTable();
        }

        if ($this->id) {
            $this->data = $this->dataStart = $this->db->selectRow(
                "SELECT
                    *
                FROM
                    ?#
                WHERE
                    ?# = ?d",
                $this->table,
                $this->idField,
                $this->id
            );
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

        if ($key == $this->idField) {
            trigger_error("Can not change id field!", E_USER_ERROR);
        }

        return $this->data[$key] = $value;
    }

    public function __get($key)
    {
        // Нельзя получать данные из объекта который удалён
        if ($this->isDel) {
            trigger_error("Can not get value from removed object!", E_USER_ERROR);
        }

        // Если спрашивают ключевое поле, но сейчас идёт только заполнение данных то пытаетмся сделать запись в БД и вернуть id который она скажет
        if (in_array($key, [$this->idField, 'id']) && !$this->id && count($this->data)) {
            return $this->flush();
        }

        return $this->data[$key];
    }

    public function __isset($key)
    {
        // Нельзя получать данные из объекта который удалён
        if ($this->isDel) {
            trigger_error("Can not get value from removed object!", E_USER_ERROR);
        }

        // Если спрашивают ключевое поле, но сейчас идёт только заполнение данных то пытаетмся сделать запись в БД и вернуть id который она скажет
        if (in_array($key, [$this->idField, 'id']) && !$this->id && count($this->data)) {
            return $this->flush();
        }

        return isset($this->data[$key]);
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

        if (count($this->data)) {
            if ($this->id) {
                if ($this->dataStart != $this->data) {
                    $data = [];
                    foreach ($this->data as $k => $v) {
                        if ($k == $this->idField) {
                            continue;
                        }
                        $data[$k] = $v;
                    }

                    $ret = $this->db->query("UPDATE
                                                ?#
                                            SET
                                                ?a
                                            WHERE
                                                ?# = ?d",
                        $this->table,
                        $data,
                        $this->idField,
                        $this->id
                    );

                    $this->dataStart = $this->data;
                }

                $ret = $this->id;
            } else {
                $ret = $this->db->query("INSERT INTO
                                            ?#(?#)
                                        VALUES
                                            (?a)",
                    $this->table,
                    array_keys($this->data),
                    array_values($this->data)
                );

                $this->id = $this->data[$this->idField] = $ret;
            }
        }

        return $ret;
    }

    public function isExists()
    {
        return $this->db->selectCell("SELECT COUNT(*) FROM ?# WHERE ?# = ?d", $this->table, $this->idField, $this->id) > 0;
    }

    public function hasChanges()
    {
        return $this->data != $this->dataStart;
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
        return $this->data;
    }
}
