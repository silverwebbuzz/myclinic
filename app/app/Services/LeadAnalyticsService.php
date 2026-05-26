<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Admin analytics for the public-directory lead pipeline.
 * Tables it reads (all written by the marketing site):
 *   - directory_leads
 *   - directory_doctors
 *   - directory_sms_quotas
 *   - directory_sms_settings
 */
final class LeadAnalyticsService
{
    /** High-level KPI tiles for the dashboard top strip. */
    public static function kpis(): array
    {
        $db = Database::connection();
        $stmt = $db->query(
            "SELECT
                SUM(type = 'book_submitted') AS book_submitted_total,
                SUM(type = 'book_submitted' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS book_submitted_7d,
                SUM(type = 'book_submitted' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS book_submitted_30d,
                SUM(sms_status = 'sent') AS sms_sent_total,
                SUM(sms_status = 'sent' AND sms_sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS sms_sent_30d,
                SUM(doctor_viewed_at IS NOT NULL) AS doctor_views_total
             FROM directory_leads"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Conversion rate: leads where the doctor opened the landing page,
        // divided by SMS sent. (Captures "did the SMS work?")
        $sent = max(1, (int) ($row['sms_sent_total'] ?? 0));
        $row['doctor_view_rate'] = round((int) ($row['doctor_views_total'] ?? 0) * 100 / $sent, 1);

        return $row;
    }

    /** Last 30 days, grouped by day — for a sparkline. */
    public static function dailySeries(int $days = 30): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT DATE(created_at) AS d, COUNT(*) AS n
             FROM directory_leads
             WHERE type = 'book_submitted' AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             GROUP BY DATE(created_at)
             ORDER BY d ASC"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Recent leads — newest first, joined with doctor + patient. */
    public static function recent(int $limit = 50): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT dl.*, dd.name AS clinic_name, dd.doctor_name, dd.city AS clinic_city,
                    dd.area AS clinic_area, dd.is_claimed,
                    pi.name AS patient_name, pi.phone AS patient_phone
             FROM directory_leads dl
             JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
             LEFT JOIN patient_identities pi ON pi.id = dl.patient_identity_id
             WHERE dl.type = 'book_submitted'
             ORDER BY dl.created_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Top doctors by lead volume — this is the sales call list. */
    public static function topDoctorsBySmsLeads(int $limit = 25, int $days = 30): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT dd.id, dd.name, dd.doctor_name, dd.city, dd.area, dd.phone,
                    dd.specialty, dd.is_claimed,
                    COUNT(dl.id) AS lead_count,
                    SUM(dl.sms_status = 'sent') AS sms_sent,
                    SUM(dl.doctor_viewed_at IS NOT NULL) AS landing_views,
                    MAX(dl.created_at) AS last_lead_at
             FROM directory_leads dl
             JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
             WHERE dl.type = 'book_submitted'
               AND dl.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY dd.id
             ORDER BY lead_count DESC, dd.is_claimed ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** How SMS attempts broke down by suppression reason. */
    public static function smsStatusBreakdown(int $days = 30): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT sms_status, COUNT(*) AS n
             FROM directory_leads
             WHERE type = 'book_submitted'
               AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY sms_status"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cities with the highest unmet demand (lead count on unclaimed clinics). */
    public static function topCitiesUnclaimed(int $limit = 10, int $days = 30): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT dd.city, dd.state, COUNT(dl.id) AS lead_count,
                    COUNT(DISTINCT dd.id) AS clinics
             FROM directory_leads dl
             JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
             WHERE dl.type = 'book_submitted'
               AND dd.is_claimed = 0
               AND dl.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY dd.city, dd.state
             ORDER BY lead_count DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
