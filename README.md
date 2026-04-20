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
