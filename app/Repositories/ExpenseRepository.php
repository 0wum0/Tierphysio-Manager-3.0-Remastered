<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class ExpenseRepository extends Repository
{
    protected string $table = 'expenses';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function getPaginated(int $page, int $perPage, string $category = '', string $search = ''): array
    {
        $where  = [];
        $params = [];

        if ($category !== '') {
            $where[]  = '`category` = ?';
            $params[] = $category;
        }
        if ($search !== '') {
            $where[]  = '(`description` LIKE ? OR `supplier` LIKE ? OR `notes` LIKE ?)';
            $s = '%' . $search . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $whereStr = $where ? implode(' AND ', $where) : '';
        return $this->paginate($page, $perPage, $whereStr, $params, 'date', 'DESC');
    }

    public function getStats(): array
    {
        $exp = $this->t('expenses');
        $month = date('Y-m-01');
        $year  = date('Y-01-01');

        $totalAll = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount_gross), 0) FROM `{$exp}`"
        );
        $totalMonth = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount_gross), 0) FROM `{$exp}` WHERE `date` >= ?",
            [$month]
        );
        $totalYear = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount_gross), 0) FROM `{$exp}` WHERE `date` >= ?",
            [$year]
        );
        $count = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM `{$exp}`");
        $countMonth = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$exp}` WHERE `date` >= ?",
            [$month]
        );

        $categories = $this->db->fetchAll(
            "SELECT `category`, COALESCE(SUM(amount_gross), 0) AS total, COUNT(*) AS cnt
             FROM `{$exp}` GROUP BY `category` ORDER BY total DESC"
        );

        return [
            'total_all'    => $totalAll,
            'total_month'  => $totalMonth,
            'total_year'   => $totalYear,
            'count'        => $count,
            'count_month'  => $countMonth,
            'categories'   => $categories,
        ];
    }

    public function getCategories(): array
    {
        $exp = $this->t('expenses');
        $rows = $this->db->fetchAll("SELECT DISTINCT `category` FROM `{$exp}` ORDER BY `category`");
        return array_column($rows, 'category');
    }

    public function getMonthlyTotals(int $months = 12): array
    {
        $exp   = $this->t('expenses');
        $from  = date('Y-m-01', strtotime("-{$months} months"));
        return $this->db->fetchAll(
            "SELECT DATE_FORMAT(`date`, '%Y-%m') AS month,
                    COALESCE(SUM(amount_gross), 0) AS total
             FROM `{$exp}`
             WHERE `date` >= ?
             GROUP BY month
             ORDER BY month ASC",
            [$from]
        );
    }
}
