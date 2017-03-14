<?php

use \Illuminate\Pagination\Paginator;

function make_pagination ($query, $pageNumber = null, $pageSize = 20) 
{
    if (!$pageNumber) {
      $pageNumber = 1;
    }
    if ($pageSize == null) {
      return $query->get();
    } else {
      \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($pageNumber) {
          return $pageNumber;
      });
      return $query->paginate($pageSize);
    }
}