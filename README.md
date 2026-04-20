# JerseyFlow - Football Jersey E-Commerce Platform

A web-based e-commerce application for browsing and purchasing football jerseys online, built with PHP and MySQL.

## Features

- **User Authentication**: Register, login and manage user profiles
- **Product Listings**: Browse a wide catalog of football jerseys with details and images
- **Search & Filters**: Find jerseys by team, product name or price range
- **Shopping Cart**: Add, update and remove items from your cart
- **Checkout & Payments**: Smooth checkout flow with simulated payment processing *(real payment gateway integration planned for future release)*
- **Order Management**: View and track your orders from your dashboard
- **Admin Panel**: Manage products, categories, orders and users
- **Responsive Design**: Fully functional on desktop only 

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript, jQuery, AJAX
- **Backend**: PHP
- **Database**: MySQL
- **Server**: XAMPP / WAMP (Localhost)

## Installation

### Prerequisites

- XAMPP or WAMP server installed
- PHP 7.0 or higher
- MySQL 5.7 or higher

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/AshwinMaharjan/jerseyflow-ecommerce.git
   ```

2. **Move to your server root**
   - For XAMPP: place the folder inside `htdocs/`
   - For WAMP: place the folder inside `www/`

3. **Import the database**
   - Open phpMyAdmin
   - Create a new database named `jerseyflow_new`
   - Import the SQL file from `jerseyflow_new.sql`

4. **Configure database credentials**

   Open `connect.php` and update:
   ```php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'root');
   define('DB_PASSWORD', '');
   define('DB_NAME', 'jerseyflow_new');
   ```

5. **Run the application**
   - Start your Apache and MySQL servers from the XAMPP/WAMP control panel
   - Visit the app at: `http://localhost/jerseyflow-ecommerce`

## Usage

### For Users
- Register or log in to your account
- Browse or search for football jerseys
- Filter by team, size or price
- Add jerseys to your cart
- Proceed through checkout and complete a (simulated) payment
- View your orders from your profile dashboard

### For Admin
- Log in with admin credentials
- Add, edit or remove jersey listings and categories
- View and manage customer orders
- Monitor user accounts and activity

## Project Structure

```
jerseyflow-ecommerce/
├── admin/              # Admin panel pages and logic
├── assets/             # Static assets (JS, images, fonts)
├── homepage/
├── api/            
├── script/
├── users/
├── style/                # Stylesheets
├── images/             # Product and site images
├── uploads/            # Uploaded product images
├── homepage.php           # Application entry point
└── jerseyflow_new.sql      # Database schema and seed data
```

## Screenshots
- Landing Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/242771fb-a8c1-41b6-9458-857d2c854b59" />
- Featured Products: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/0d3eef1d-4268-49e5-8fb2-9c9508aeee8d" />
- Browse by Top Clubs: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/b502b6b7-9b97-4f6d-ac3f-b461af145bdc" />
- World Cup Collection: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/bb8e90d5-3e0a-4ce1-8b60-b64adfad0121" />
- Retro Jersey: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/11699db4-f50d-41e4-aaee-7dda22a3f4eb" />
- Limited Edition: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/5bea5c2e-7624-4e98-a362-a45c5bef3afb" />
- Standard Jersey Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/7cb72492-7be8-40e1-9866-bb06160cc1bb" />
- Retro Jersey Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/a69c70e9-8ec8-40bc-81cc-c84222c1f619" />
- Limited Edition Jersey Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/0d35faa1-7c08-4d74-876e-04e17e8c9f34" />
- Player Edition Jersey Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/41d3b082-cf95-4372-a259-03b352a2b177" />
- FIFA 2026 Jersey Page:  <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/88bf88e5-4bf4-415c-b21b-cecaecaa7c23" />
- All Club Page Jersey: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/4793f0a3-04d4-4591-9fac-04d6c5ddce9f" />
- Jersey Description Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/e400009a-5245-44f5-8d8d-a4a1328bb1c6" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/7832cfd7-0da4-48ca-89f0-23c2e3b3ccd3" />
- Login Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/f5089a29-e42c-42ac-8941-26ca49c05f17" />
- Register Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/ec7cd978-28a2-466b-9fc3-7aef2ff09e3c" />
- Admin Dashboard: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/9186d196-1bbf-47da-8570-d7343c433361" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/69435ddc-218f-42f9-a9b7-5c4942f5632b" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/ac7a49c2-bb77-4a2e-a41a-2aad27bfda6b" />
- Add New Products: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/17f2b061-1baf-41f5-b2b0-920922bc9c64" />
- All Products Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/01af0bae-7bf9-4a6e-80d0-87dd064d5655" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/5ade3831-d8b4-46ca-b624-082a95580c9f" />
- Categories Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/061ffa79-114a-482e-a6b8-5634aeca6551" />
- Orders Management Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/2fe0ef99-3e8e-49f4-92cc-ce593a9cfcdd" />
- Users Management: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/b744210c-0408-47d0-b3c5-358a8a583126" />
- Adjust Stock Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/1239607d-7aab-48e7-8abd-79c75bdfaff0" />
- Low Stock Alerts Page:  <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/877524a8-aec1-42df-9d16-91839a9a42c7" />
- Payement Transaction Page:  <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/2a48c327-09d3-4e47-9cc3-edbe0a148806" />
- Payment Reports Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/98ffb4ca-e4b3-4186-bd56-8638eb4a8f05" />  <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/0a05f711-8739-40e0-91f8-54419c760cff" />
- Profile Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/81f88f00-2cca-4e5b-b703-b414e2886944" />
- Change Password Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/bc29cfba-bd37-4c1f-9037-7b76e0eecca0" /> 
- Customers Dashboard: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/de865ded-1509-4e7f-88e2-aa5ebc39c50e" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/0568413a-8b92-463d-9400-0c3bda3e6d79" />
- Customers Order Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/2002ccfe-b09a-4219-b84e-2c6d403e5fdd" />
- Customers Profile Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/c1f7302f-53fc-41ef-9842-72d1205fee17" />
- Customers Address Book Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/082e387b-6526-47e5-b2a9-e8bbf2c13c3b" /> <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/73646946-cdb0-450f-971f-d6d2795c6261" />
- Customers Change Password Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/eeb6ee6b-8bb8-4434-8d54-edd3cc92aa29" />
- Customers Cart Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/1ef506f6-95f1-4709-a969-60bb711c13ec" />
- Customers Checkout Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/17b1729a-4266-4358-bcc9-6f655b8f7e73" />
- Customers COD Page: <img width="1351" height="1103" alt="localhost_jerseyflow-ecommerce_users_invoice php_order_id=6" src="https://github.com/user-attachments/assets/f8236782-2118-44e6-a72d-4376034bd0ca" />
- Customers Esewa Page: <img width="1366" height="768" alt="image" src="https://github.com/user-attachments/assets/df12958b-ce46-4b54-8ea8-fbce6b6f84fb" />


## Roadmap

- [ ] Real payment gateway integration (e.g., Stripe, eSewa, PayPal)
- [ ] Email notifications for order confirmation
- [ ] Wishlist / saved items feature
- [ ] Product reviews and ratings
- [ ] Discount codes and promotions

## Contact

Have questions or want to contribute? Reach out:

- **GitHub**: https://github.com/AshwinMaharjan
- **Email**: maharjan.ashwin098@gmail.com

---

> Built with ⚽ and PHP. JerseyFlow — wear your passion.
