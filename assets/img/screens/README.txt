Product screenshots for the gallery on /clinic-management-software
(rendered by partials/screenshot-gallery.php).

Files currently wired up — replacing any of them updates the site with no code
change; a missing file is skipped, so the section never shows a broken tile:

  dashbord.png             Dashboard — today's appointments, payment, revenue
  Patient-visit.png        Consultation / EMR screen
  Calender.png             Calendar, month view
  Book-an-appointment.png  Calendar with the "Book appointment" popup open
  Walk-in.png              Walk-in appointment form
  Report.png               Income & GST report

To add or rename one, edit the $ecpShots list at the top of
partials/screenshot-gallery.php (that is also where the captions live).

Guidance: PNG or JPG, roughly 2400px wide. Keep real patient data out of them —
the demo clinic is fine.
