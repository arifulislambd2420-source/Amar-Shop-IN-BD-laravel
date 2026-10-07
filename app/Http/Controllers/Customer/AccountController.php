<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customer account: order history, order details and saved addresses.
 *
 * Orders are not linked to `users` by id (guests can order too), so a
 * customer's orders are those placed with their phone number — the same
 * phone-is-the-identity rule order tracking uses. Every lookup below is
 * scoped to the signed-in customer, so another customer's order id or
 * address id is a 404, never a leak.
 */
class AccountController extends Controller
{
    private const MAX_ADDRESSES = 5;

    public function index(Request $request)
    {
        $user = $request->user();

        return view('customer.account', [
            'user' => $user,
            'orders' => $this->ordersOf($user)->with('items')->latest('created_at')->paginate(10),
            'addresses' => $user->addresses()->orderByDesc('is_default')->latest('id')->get(),
            'districts' => config('districts'),
        ]);
    }

    public function order(Request $request, int $order)
    {
        $order = $this->ordersOf($request->user())->with('items')->whereKey($order)->firstOrFail();

        return view('customer.order', ['order' => $order]);
    }

    public function storeAddress(Request $request)
    {
        $user = $request->user();

        if ($user->addresses()->count() >= self::MAX_ADDRESSES) {
            return back()->withErrors(['address' => 'সর্বোচ্চ '.self::MAX_ADDRESSES.'টি ঠিকানা সেভ করা যায়। আগে একটি মুছুন।'], 'address');
        }

        $data = $this->validated($request);
        $makeDefault = $request->boolean('is_default') || ! $user->addresses()->exists();

        $address = $user->addresses()->create($data + ['is_default' => false]);

        if ($makeDefault) {
            $this->makeDefault($address);
        }

        return redirect()->to(route('customer.account').'#addresses')->with('status', 'ঠিকানা সেভ হয়েছে।');
    }

    public function updateAddress(Request $request, int $address)
    {
        $address = $request->user()->addresses()->whereKey($address)->firstOrFail();

        $address->update($this->validated($request));

        if ($request->boolean('is_default')) {
            $this->makeDefault($address);
        }

        return redirect()->to(route('customer.account').'#addresses')->with('status', 'ঠিকানা আপডেট হয়েছে।');
    }

    public function destroyAddress(Request $request, int $address)
    {
        $address = $request->user()->addresses()->whereKey($address)->firstOrFail();
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault && ($next = $request->user()->addresses()->latest('id')->first())) {
            $this->makeDefault($next);
        }

        return redirect()->to(route('customer.account').'#addresses')->with('status', 'ঠিকানা মুছে ফেলা হয়েছে।');
    }

    public function defaultAddress(Request $request, int $address)
    {
        $this->makeDefault($request->user()->addresses()->whereKey($address)->firstOrFail());

        return redirect()->to(route('customer.account').'#addresses')->with('status', 'ডিফল্ট ঠিকানা বদলানো হয়েছে।');
    }

    private function ordersOf($user)
    {
        return Order::query()->whereIn('phone', $user->orderPhones());
    }

    private function makeDefault(CustomerAddress $address): void
    {
        CustomerAddress::where('user_id', $address->user_id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validateWithBag('address', [
            'label' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'district' => ['required', Rule::in(config('districts'))],
            'thana' => ['required', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        $data['phone'] = preg_replace('/[^0-9]/', '', $data['phone']);

        return $data;
    }
}
