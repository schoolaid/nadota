<?php

namespace SchoolAid\Nadota\Http\Traits;

trait ResourcePagination
{
    protected int $perPage = 10;
    protected array $allowedPerPage = [10, 20, 50, 100];
    protected int $page = 1;

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
    }

    public function getAllowedPerPage(): array
    {
        return $this->allowedPerPage;
    }

    public function setAllowedPerPage(array $allowedPerPage): void
    {
        $this->allowedPerPage = $allowedPerPage;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getPagination(): array
    {
        return [
            'per_page' => $this->getPerPage(),
            'page' => $this->getPage(),
        ];
    }

    public function setPagination(array $pagination): void
    {
        $this->setPerPage($pagination['per_page']);
        $this->setPage($pagination['page']);
    }
}
