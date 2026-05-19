USE manageclinic;

-- Sprint 12 performance indexes (apply if k6 p99 > 300ms on hot paths)
CREATE INDEX idx_patients_clinic_phone ON patients (clinic_id, phone);
CREATE INDEX idx_appointments_clinic_scheduled ON appointments (clinic_id, scheduled_at);
CREATE INDEX idx_visits_clinic_status_visited ON visits (clinic_id, status, visited_at);
CREATE INDEX idx_notifications_queue ON notifications (status, scheduled_at);
