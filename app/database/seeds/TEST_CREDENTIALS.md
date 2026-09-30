# Store test data — logins and scenarios

Created by `app/database/seeds/store_test_seed.php`. **Everything here is TEST data on the
live database.** Remove it all with `php database/seeds/store_test_seed.php --wipe`.

## Run it (on the server)

```bash
cd /home/silverwebbuzz_in/public_html/eclinicpro/app
# 1. once: let test customers sign in with OTP 123456
echo "STORE_TEST_OTP=1" >> .env
# 2. seed (removes any previous test data first; ~10 s)
sudo -u silverwebbuzz_in php database/seeds/store_test_seed.php
# 3. when testing is finished: remove ALL test data, then switch the OTP shortcut off
sudo -u silverwebbuzz_in php database/seeds/store_test_seed.php --wipe
sed -i '/^STORE_TEST_OTP=/d' .env
```

Run it as the site user (not root) so the generated product images belong to the site.
The seeder sends **no** emails / WhatsApp / SMS and makes **no** Razorpay calls.

## Sellers — https://app.eclinicpro.com/vendor/login — password `Test@12345` for all

| Email | Store | Status | Products | Notes |
|---|---|---|---|---|
| test.seller01@example.com | Test Wellness Pharma | approved | 15 | own commission 12%, featured, **payout PAID** (UTR TESTUTR0000123456), staff login test.staff01@example.com |
| test.seller02@example.com | Test Ayur Naturals | approved | 14 | one product has a fixed ₹25/unit commission, one product rejected |
| test.seller03@example.com | Test Baby Care Co | approved | 11 | featured, **payout APPROVED**, staff login test.staff03@example.com is **disabled** |
| test.seller04@example.com | Test Surgicals & Devices | approved | 11 | own commission 8%, 10-day return window, one product disabled by admin |
| test.seller05@example.com | Test FitFuel Nutrition | approved | 12 | 15% on sports nutrition (seller + category rule), **payout REQUESTED** by seller, expired FSSAI doc, ₹100 penalty |
| test.seller06@example.com | Test Skin Studio | approved | 10 | **payout request DECLINED**, funds the TESTSELLER15 coupon |
| test.seller07@example.com | Test Smile Oral Care | approved | 11 | a rejected KYC document |
| test.seller08@example.com | Test Her Health | approved | 10 | a pending KYC document |
| test.seller09@example.com | Test Calm Mind Co | approved | 9 | one product rejected |
| test.seller10@example.com | Test Green Pantry | approved | 10 | bank account **pending** verification |
| test.seller11@example.com | Test Vision Plus | approved | 10 | |
| test.seller12@example.com | Test Home Wellness | approved | 10 | **no GSTIN** → bill of supply instead of tax invoice |
| test.seller13@example.com | Test Men's Vitality | pending_review | 5 | awaiting admin approval |
| test.seller14@example.com | Test Herbal Roots | rejected | 4 | GST certificate rejected |
| test.seller15@example.com | Test QuickMeds | suspended | 8 | products hidden from the store while suspended |

Every seller: pickup / return / registered addresses, GST + PAN + cheque documents, licences
(FSSAI / AYUSH / medical device) matching their categories. Bank accounts are created only when
`STORE_DATA_KEY` is set in `app/.env` (they are encrypted with it).

## Customers — sign in on eclinicpro.com (or the app) with OTP `123456`

Needs `STORE_TEST_OTP=1` in `app/.env`. The shortcut works only for mobiles 9000000000–9000000099
that have no account or a seeded test account (email `test.patientNN@example.com`, never delivered);
those never get a real WhatsApp. A real patient's number, or any number with the flag off, gets the
normal WhatsApp OTP. The seeder refuses to run if a real patient already uses one of these numbers.

| Mobile | Name | Orders (ECS…-TST###) |
|---|---|---|
| 9000000001 | Priya Sharma | TST001 awaiting payment · TST024 completed with TESTSAVE10 — has a cart |
| 9000000002 | Rahul Verma | TST002 payment failed · TST027 partially shipped (3 sellers) — cart with coupon |
| 9000000003 | Sneha Iyer | TST003 expired · TST028 partially delivered (2 sellers) — wishlist |
| 9000000004 | Amit Patel | TST004 cancelled before payment · TST029 cancelled by customer (refunded) |
| 9000000005 | Neha Kapoor | TST005 new, awaiting 2 sellers · TST030 rejected by seller (refunded) — cart with a price change |
| 9000000006 | Vikram Singh | TST006 accepted · TST031 cancelled by admin |
| 9000000007 | Anjali Nair | TST007 packed (mobile order) · TST032 auto-cancelled, seller missed 48 h |
| 9000000008 | Karan Mehta | TST008 ready to ship · TST033 1 unit cancelled, rest delivered |
| 9000000009 | Pooja Reddy | TST009 in transit · TST025 delivered with seller coupon TESTSELLER15 |
| 9000000010 | Siddharth Rao | TST010 out for delivery · TST034 RTO, refunded (delivery fee kept) |
| 9000000011 | Riya Bose | TST011 delivered (can return) · TST035 lost in transit, refunded |
| 9000000012 | Manish Gupta | TST012 delivered · TST036 return **requested** |
| 9000000013 | Kavita Joshi | TST013 delivered · TST037 return **approved** |
| 9000000014 | Arjun Desai | TST014 delivered · TST038 return **pickup scheduled** |
| 9000000015 | Divya Menon | TST015 completed · TST039 return **picked up** |
| 9000000016 | Rohit Malhotra | TST016 completed · TST040 return **received** by seller |
| 9000000017 | Aishwarya Pillai | TST017 completed · TST041 return **QC failed** (admin decides) |
| 9000000018 | Sanjay Kumar | TST018 completed · TST042 return **rejected** |
| 9000000019 | Meghna Das | TST019 completed · TST043 return **refunded** (1 of 2 units) |
| 9000000020 | Tarun Chawla | TST020 completed · TST044 return **refunded** (whole package) |
| 9000000021 | Nisha Agarwal | TST021 completed |
| 9000000022 | Harsh Vora | TST022 completed |
| 9000000023 | Lakshmi Krishnan | TST023 completed |
| 9000000024 | Farhan Qureshi | TST026 new, free delivery with TESTFREESHIP |
| 9000000025 | Blocked Customer (Test) | TST045 completed — account **blocked** from ordering |

Order numbers look like `ECS260930-TST001` (the date part is the day it was "placed").
Seller-side screens also show 16 reviews (published, with/without seller reply, pending,
rejected), GST invoices and credit notes in each test seller's own series (`I{id}-2627-n`),
and a ledger with sales, commission, GST on commission, courier charges, return reversals,
a goodwill credit (Baby Care) and a penalty (FitFuel).

## Coupons (all limited to test sellers' products)

| Code | Rule |
|---|---|
| TESTSAVE10 | 10% off, up to ₹150, on ₹299+ — Test Wellness Pharma, eClinicPro-funded |
| TESTFLAT50 | ₹50 off on ₹499+ — Test Baby Care Co |
| TESTFREESHIP | free delivery — Test Smile Oral Care |
| TESTSELLER15 | 15% off up to ₹200, funded by Test Skin Studio |
| TESTEXPIRED | ended a week ago (shows the "expired" message) |

## Good to know while testing

- **Paying for TST001**: "Pay now" opens Razorpay **sandbox** (store is in sandbox mode) — use a
  Razorpay test card and the order becomes paid for real (sandbox), end to end.
- **Refunds on seeded orders**: seeded payments have fake ids (`pay_TEST…`), so cancelling or
  refunding a *seeded* paid order from the screens will show "refund could not be processed".
  To test live refunds, place a new order and pay with a Razorpay test card first.
- The maintenance job (every 10 min) behaves normally on test data: TST001 expires after 3 days,
  delivered packages move to *completed* when their return window ends.
- Test sellers never appear in eClinicPro's own GST series (ECP-…): no delivery-charge invoices
  and no monthly seller invoices are issued for them (see `StoreTestData`).
- The store is live: a **real** customer could buy a test product. `--wipe` then keeps that
  seller (closed, products archived) instead of deleting it, and prints the order numbers.
