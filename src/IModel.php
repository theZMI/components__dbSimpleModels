<?php

namespace Models;

interface IModel
{
    public function isExists();

    public function isOnlyShow();

    public function isDeleted();

    public function flush();

    public function delete();
}
