<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking could not be made. The message is safe to show to the person booking.
 */
class BookingException extends RuntimeException
{
    public static function roomUnavailable(): self
    {
        return new self(__('Sorry, that room is no longer available for those dates. Please search again.'));
    }

    public static function overCapacity(int $capacity): self
    {
        return new self(__('That room sleeps up to :capacity guests.', ['capacity' => $capacity]));
    }

    public static function roomNotReady(string $number): self
    {
        return new self("Room {$number} is not clean yet, so the guest cannot be checked in now.");
    }

    public static function depositTooHigh(): self
    {
        return new self('The deposit cannot be more than the total price of the stay.');
    }

    public static function guestInHouse(): self
    {
        return new self(__('This guest is already checked in. A guest in the house cannot book another room for the same nights. To add a room for the same party use a group booking, or use Change room to move the guest.'));
    }

    public static function guestAlreadyBooked(): self
    {
        return new self(__('This guest already has a booking for some of these nights. Change that booking instead, or choose different dates.'));
    }

    public static function cannotCancel(): self
    {
        return new self(__('Only a confirmed booking can be cancelled.'));
    }

    public static function notArrivalDay(string $date): self
    {
        return new self("This booking starts on {$date}. Guests can be checked in from the day of arrival; to change the dates use Change booking.");
    }

    public static function stayOver(): self
    {
        return new self('The dates of this booking have already passed. Change the dates first, then check the guest in.');
    }

    public static function cannotCheckIn(): self
    {
        return new self('Only a confirmed booking can be checked in.');
    }

    public static function notCheckedIn(): self
    {
        return new self('This guest is not checked in.');
    }
}
