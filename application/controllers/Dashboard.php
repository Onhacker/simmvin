<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends App_Controller
{
    public function index()
    {
        $this->require_permission('dashboard.view');
        $this->load->model('Debt_model', 'debt');
        $this->load->model('Finance_model', 'finance');
        $stats = array();
        $stats['events'] = (int) $this->db->where('status', 'open')->count_all_results('training_events');
        $stats['villages'] = (int) $this->db->from('registrations r')->join('training_events e','e.id=r.event_id')
            ->where(array('r.status'=>'active','e.status'=>'open'))->count_all_results();
        $stats['participants'] = (int) $this->db->from('participants p')->join('registrations r','r.id=p.registration_id')
            ->join('training_events e','e.id=r.event_id')->where(array('p.is_active'=>1,'r.status'=>'active','e.status'=>'open'))
            ->where('p.deleted_at IS NULL',NULL,FALSE)->count_all_results();
        $eventIncomeRaw = $this->db->select_sum('p.amount')->from('payments p')->join('training_events e','e.id=p.event_id')
            ->where(array('p.status'=>'verified','e.status'=>'open'))->get()->row()->amount;
        $eventExpenseRaw = $this->db->select('COALESCE(SUM(x.amount + x.admin_fee),0) total',FALSE)->from('expenses x')
            ->join('training_events e','e.id=x.event_id')->where(array('x.status'=>'verified','e.status'=>'open'))->get()->row()->total;
        $eventIncomeCents = simp_money_cents($eventIncomeRaw ?: '0');
        $eventExpenseCents = simp_money_cents($eventExpenseRaw ?: '0');
        if ($eventIncomeCents === NULL) $eventIncomeCents = 0;
        if ($eventExpenseCents === NULL) $eventExpenseCents = 0;
        $stats['income_cents'] = $eventIncomeCents;
        $stats['expenses_cents'] = $eventExpenseCents;
        $stats['income'] = simp_money_from_cents($eventIncomeCents);
        $stats['expenses'] = simp_money_from_cents($eventExpenseCents);
        $stats['remaining'] = simp_money_from_signed_cents($eventIncomeCents - $eventExpenseCents);
        // Kartu saldo dan net transaksi dihitung dari akun yang ditandai
        // "Masuk Total".  included_fund_flow juga memperhitungkan pokok
        // transfer yang melintasi batas included/excluded; transfer internal
        // hanya mengurangi biaya admin.
        $financeStats = $this->finance->included_fund_flow();
        $financeStats['scope_label'] = 'Akun yang masuk total';
        $registrationSummary = $this->db->select('COUNT(r.id) villages,COALESCE(SUM(r.expected_amount),0) total_due', FALSE)
            ->from('registrations r')->join('training_events e','e.id=r.event_id')
            ->where(array('r.status'=>'active','e.status'=>'open'))->get()->row_array();
        $registrationSummary['villages'] = (int)($registrationSummary['villages'] ?: 0);
        $registrationSummary['participants'] = $stats['participants'];
        $registrationDueCents = simp_money_cents($registrationSummary['total_due'] ?: '0');
        if ($registrationDueCents === NULL) $registrationDueCents = 0;
        $registrationSummary['total_due_cents'] = $registrationDueCents;
        $registrationSummary['total_due'] = simp_money_from_cents($registrationDueCents);
        // Use verified payments for the dashboard, matching the finance reports.
        $registrationSummary['total_paid'] = $stats['income'];
        $registrationPaidCents = simp_money_cents($registrationSummary['total_paid']);
        if ($registrationPaidCents === NULL) $registrationPaidCents = 0;
        $registrationSummary['total_paid_cents'] = $registrationPaidCents;
        $registrationSummary['total_paid'] = simp_money_from_cents($registrationPaidCents);
        $registrationRemainingCents = max(0, $registrationDueCents - $registrationPaidCents);
        $registrationSummary['remaining_cents'] = $registrationRemainingCents;
        $registrationSummary['remaining'] = simp_money_from_cents($registrationRemainingCents);
        $accounts = $this->db->query("SELECT a.*, a.opening_balance + COALESCE(SUM(CASE WHEN l.direction='in' THEN l.amount ELSE -l.amount END),0) AS balance FROM fund_accounts a LEFT JOIN ledger_entries l ON l.account_id=a.id WHERE a.is_active=1 GROUP BY a.id ORDER BY a.sort_order,a.name")->result_array();
        $latestEvents = $this->db->select('e.*, COUNT(DISTINCT er.id) regency_count, COUNT(DISTINCT r.id) village_count')
            ->from('training_events e')->join('event_regencies er', 'er.event_id=e.id', 'left')->join('registrations r', 'r.event_id=e.id AND r.status="active"', 'left', FALSE)
            ->where('e.status','open')->group_by('e.id')->order_by('e.start_date', 'DESC')->limit(5)->get()->result_array();
        $debtSummary = $this->Auth_model->can('debts.view') && $this->db->table_exists('company_debts')
            ? $this->debt->summary()
            : array();
        $this->render('dashboard/index', array(
            'pageTitle' => 'Dashboard',
            'stats' => $stats,
            'accounts' => $accounts,
            'latestEvents' => $latestEvents,
            'registrationSummary' => $registrationSummary,
            'financeStats' => $financeStats,
            'debtSummary' => $debtSummary
        ));
    }
}
