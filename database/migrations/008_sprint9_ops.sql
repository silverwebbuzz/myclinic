-- Sprint 9: doctor incentive flat fee, optional calendar stub flag on specialty config via JSON

ALTER TABLE users
  ADD COLUMN incentive_flat_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER incentive_percent;
