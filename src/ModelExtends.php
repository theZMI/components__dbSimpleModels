<?php

namespace Models;

class ModelExtends extends ModelOptimized
{
    const PAGE_ALL   = -1;
    const PAGE_LIMIT = 100;

    protected function createTable()
    {
    }

    public function __construct($table, $id = null)
    {
        $db = GetDefaultDb();
        parent::__construct(
            $db,
            $table,
            'id',
            $id,
            false
        );
    }

    public function flush()
    {
        if (
            $this->hasChanges()
            && !$this->isExists()
            && !$this->isDeleted()
            && !$this->isOnlyShow()
        ) {
            $this->create_time = time();
        }
        return parent::flush();
    }

    public function __debugInfo()
    {
        return $this->getData();
    }

    public function getList($page = self::PAGE_ALL)
    {
        $from = $page ? (intval($page - 1) * static::PAGE_LIMIT) : 0;
        $ids  = $this->db->selectCol(
            "SELECT `id` FROM ?# WHERE 1 ORDER BY `create_time` DESC {LIMIT ?d}{, ?d}",
            $this->table,
            $page === self::PAGE_ALL ? DBSIMPLE_SKIP : $from,
            $page === self::PAGE_ALL ? DBSIMPLE_SKIP : static::PAGE_LIMIT
        );
        $ret  = [];
        foreach ($ids as $id) {
            $ret[$id] = new static($id);
        }
        return $ret;
    }
}
