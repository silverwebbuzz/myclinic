Product screenshots for the gallery on /clinic-management-software
(rendered by partials/screenshot-gallery.php).

The gallery SCANS this folder — every .png/.jpg/.webp in here becomes a tile,
so adding or replacing a screenshot needs no code change. Delete a file and its
tile disappears; if the folder is empty the whole section is skipped.

Titles and captions are matched by file name (without extension, case-
insensitive) in the $ecpCaptions map at the top of the partial. A file that is
not in that map still shows, titled from its file name — add an entry there to
give it a proper caption.

Current files:
  dashbord.png             Dashboard — today's appointments, payment, revenue
  Patient-visit.png        Consultation / EMR screen
  Calender.png             Calendar, month view
  Book-an-appointment.png  Calendar with the "Book appointment" popup open
  Walk-in.png              Walk-in appointment form
  Report.png               Income & GST report

Guidance: PNG or JPG, roughly 2400px wide. Keep real patient data out of them —
the demo clinic is fine.

NOTE: after deploying a change to the partial, clear OPcache (cPanel -> Restart
Services -> PHP-FPM) or the server keeps running the previous version.
