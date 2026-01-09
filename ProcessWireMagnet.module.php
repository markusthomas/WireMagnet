<?php
namespace ProcessWire;

/**
 * ProcessWireMagnet
 *
 * Admin interface to view and export leads.
 * Accessible via Setup > Lead Magnets
 */
class ProcessWireMagnet extends Process
{
    public static function getModuleInfo()
    {
        return [
            'title' => 'Manage Magnet Leads',
            'summary' => 'View and export captured leads.',
            'version' => '1.0.0',
            'author' => 'Markus Thomas',
            'permission' => 'lead-magnet-view',
            'page' => [
                'name' => 'lead-magnets',
                'parent' => 'setup',
                'title' => 'Lead Magnets'
            ]
        ];
    }

    public function execute()
    {
        $out = "<h2>" . $this->_('Captured Leads') . "</h2>";

        // Export Button
        $out .= "<p><a href='./export' class='ui-button ui-widget ui-state-default ui-corner-all'><i class='fa fa-download'></i> " . $this->_('Export CSV') . "</a></p>";

        /** @var MarkupAdminDataTable $table */
        $table = $this->modules->get('MarkupAdminDataTable');
        $table->setEncodeEntities(false);

        // Check for download_count column existence (backward compatibility)
        $hasDownloadCount = true;
        try {
            $check = $this->database->query("SELECT download_count FROM leads_archive LIMIT 1");
        } catch (\Exception $e) {
            $hasDownloadCount = false;
        }

        // Check for confirmed column existence
        $hasConfirmed = true;
        try {
            $check = $this->database->query("SELECT confirmed FROM leads_archive LIMIT 1");
        } catch (\Exception $e) {
            $hasConfirmed = false;
        }

        $header = [$this->_('ID'), $this->_('Email'), $this->_('Magnet ID'), $this->_('Field'), $this->_('Date')];
        if ($hasConfirmed)
            $header[] = $this->_('Confirmed');
        $header[] = $this->_('IP');
        if ($hasDownloadCount)
            $header[] = $this->_('Downloads');
        $table->headerRow($header);

        // Fetch leads (limit 100 for now, could be paginated)
        $sql = "SELECT * FROM leads_archive ORDER BY created_at DESC LIMIT 500";
        try {
            $query = $this->database->prepare($sql);
            $query->execute();
            while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $tableRow = [
                    (int) $row['id'],
                    $this->sanitizer->entities($row['email']),
                    (int) $row['magnet_id'],
                    isset($row['magnet_field_name']) ? $row['magnet_field_name'] : 'lead_file',
                    $row['created_at']
                ];
                if ($hasConfirmed)
                    $tableRow[] = $row['confirmed'] ? $this->_('Yes') : $this->_('No');
                $tableRow[] = $this->sanitizer->entities($row['ip_address']);

                if ($hasDownloadCount)
                    $tableRow[] = (int) $row['download_count'];

                $table->row($tableRow);
            }
        } catch (\Exception $e) {
            return "<p class='error'>Error fetching leads: " . $e->getMessage() . "</p>";
        }

        $out .= $table->render();

        return $out;
    }

    public function executeExport()
    {
        $sql = "SELECT * FROM leads_archive ORDER BY created_at DESC";
        $query = $this->database->prepare($sql);
        $query->execute();
        $leads = $query->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($leads)) {
            $this->error($this->_("No leads found to export."));
            $this->session->redirect('../');
        }

        // Clear buffer
        if (ob_get_level())
            ob_end_clean();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_export_' . date('Y-m-d_H-i') . '.csv"');

        $fp = fopen('php://output', 'w');
        fputs($fp, "\xEF\xBB\xBF"); // BOM
        fputcsv($fp, array_keys($leads[0])); // Header
        foreach ($leads as $lead) {
            fputcsv($fp, $lead);
        }
        fclose($fp);
        exit;
    }
}