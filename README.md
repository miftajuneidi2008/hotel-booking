<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<h1 align="center">Hotel Booking System</h1>

<p align="center">A Laravel web application where guests search for available rooms and book their stay online, and administrators manage rooms, availability and reservations.</p>

## About the Project

The Hotel Booking System lets users find a room that fits their dates, make a reservation, and keep track of their bookings, all in one place. An admin side gives hotel staff full control over the rooms they offer and the reservations they receive.

## Features

### For Guests

- Browse rooms and search for availability by date.
- Make a reservation for an available room.
- View and manage your own bookings.

### For Administrators

- Add, edit and remove rooms.
- Manage room availability.
- View and manage all reservations.

## Tech Stack

- **Framework:** Laravel 13
- **Language:** PHP
- **Database:** MySQL (any database supported by Laravel can be configured in `.env`)
- **Package managers:** Composer and npm

## Getting Started

### Prerequisites

- PHP (version required by Laravel 13)
- Composer
- Node.js and npm
- MySQL or another supported database

### Installation

1. Clone the repository and move into the project folder:

   ```bash
   git clone <your-repository-url>
   cd <project-folder>
   ```

2. Install dependencies:

   ```bash
   composer install
   npm install
   ```

3. Create your environment file and generate the application key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Create a database, then set your credentials in `.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=hotel_booking
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. Run the migrations (add `--seed` if the project includes seeders):

   ```bash
   php artisan migrate
   ```

6. Build the frontend assets and start the app:

   ```bash
   npm run build
   php artisan serve
   ```

   The application will be available at `http://localhost:8000`.

   For development with hot reloading, run `npm run dev` in a second terminal instead of `npm run build`.

## How Booking Works

1. A guest chooses their dates and searches for available rooms.
2. The guest selects a room and confirms the reservation.
3. The booking appears in the guest's bookings, and in the admin reservations list.
4. Administrators can review reservations and keep room availability up to date.

## Running Tests

```bash
php artisan test
```

## Built With Laravel

This project is built on [Laravel](https://laravel.com), a web application framework with expressive, elegant syntax. See the [Laravel documentation](https://laravel.com/docs) to learn more about routing, Eloquent ORM, migrations and other framework features used here.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT). Add your own license here if this project uses a different one.
