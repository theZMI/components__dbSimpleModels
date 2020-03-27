<?php

namespace Models;

class ModelExtends extends ModelOptimized {
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
        return $this->data;
    }
}
