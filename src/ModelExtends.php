<?php

namespace Models;

class ModelExtends extends ModelOptimized {
    const PAGE_ALL = -1;
    const PAGE_LIMIT = 100;

    protected function CreateTable() {
    }

    public function __construct($table, $id = null) {
        $dbs = \Dbs::GetInstance();
        $db  = $dbs->GetDatabases()->db;
        parent::__construct(
            $db,
            $table,
            'id',
            $id,
            false
        );
    }

    public function Flush() {
        if ( ! $this->IsExists() && count($this->GetData())) {
            $this->create_time = time();
        }

        return parent::Flush();
    }

    public function __debugInfo() {
        return $this->GetData();
    }

    public function GetList($page = self::PAGE_ALL) {
        $from = $page ? (intval($page - 1) * static::PAGE_LIMIT) : 0;
        $ids = $this->db->selectCol(
            "SELECT `id` FROM ?# WHERE 1 ORDER BY `create_time` DESC {LIMIT ?d}{, ?d}",
            $this->table,
            $page === self::PAGE_ALL ? DBSIMPLE_SKIP : $from,
            $page === self::PAGE_ALL ? DBSIMPLE_SKIP : static::PAGE_LIMIT
        );
        $ret = [];
        foreach ($ids as $id) {
            $ret[$id] = new static($id);
        }
        return $ret;
    }
}
