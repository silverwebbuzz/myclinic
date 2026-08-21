Product screenshots for the screen-by-screen showcase on
/clinic-management-software (rendered by partials/product-showcase.php).

Each file gets its own section — image on one side, heading, description and
feature list on the other, alternating left/right down the page. A block whose
image is missing is skipped, so the page never shows a broken frame.

Current files and the section each one drives:
  dashbord.png             Dashboard — the whole day on one screen
  Patient-visit.png        Consultation — a full consultation in one page
  Calender.png             Calendar — day / week / month views
  Book-an-appointment.png  Booking — booked in under ten seconds
  Walk-in.png              Walk-ins — tokens and the queue
  Report.png               Reports — income and GST

To change the copy, edit the $ecpScreens array at the top of the partial; to
add a screen, add an entry there and drop the image in here.

Guidance: PNG or JPG, ~2400px wide at roughly 16:9 (all six are between 1.75
and 1.81 today). Keep real patient data out of them — the demo clinic is fine.
