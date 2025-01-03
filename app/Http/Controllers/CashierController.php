<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\CashierHistory; // Import model CashierHistory

class CashierController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('customer.login'); // Mengarahkan ke halaman login
        }

        // Ambil query pencarian dari parameter GET
        $search = $request->query('search');

        // Filter produk berdasarkan nama jika ada query pencarian
        $products = Product::when($search, function ($query, $search) {
            return $query->where('name', 'like', '%' . $search . '%');
        })->get();

        // Kirim data produk ke view
        return view('cashier.index', compact('products'));
    }

    public function checkout(Request $request)
    {
        $cart = $request->input('cart');
        $customerName = $request->input('customer_name'); // Ambil nama customer dari request

        if (!$cart || !is_array($cart)) {
            return response()->json(['error' => 'Keranjang kosong atau data tidak valid.'], 400);
        }

        $errors = [];
        $totalPrice = 0;
        $productNames = [];  // Array untuk menyimpan nama produk
        $transactionTime = now();  // Waktu transaksi

        // Loop untuk memproses item di keranjang
        foreach ($cart as $item) {
            $product = Product::find($item['id']);

            if (!$product) {
                $errors[] = "Produk dengan ID {$item['id']} tidak ditemukan.";
                continue;
            }

            if ($product->stock < $item['quantity']) {
                $errors[] = "Stok untuk produk '{$product->name}' tidak mencukupi.";
                continue;
            }

            // Menambahkan nama produk ke dalam array
            $productNames[] = $product->name;

            // Hitung total harga
            $totalPrice += $product->price * $item['quantity'];
            
            // Kurangi stok produk
            $product->stock -= $item['quantity'];
            $product->save();
        }

        // Jika ada error
        if (!empty($errors)) {
            return response()->json(['error' => implode('<br>', $errors)], 400);
        }

        // Gabungkan nama produk yang dibeli menjadi satu string
        $productNamesString = implode(', ', $productNames);

        // Simpan riwayat kasir
        CashierHistory::create([
            'user_id' => Auth::id(),  // ID kasir yang melakukan transaksi
            'customer_name' => $customerName,  // Simpan nama customer yang opsional
            'product_name' => $productNamesString,  // Nama produk yang dibeli, digabungkan
            'quantity' => count($cart),  // Jumlah item yang dibeli
            'total_price' => $totalPrice,  // Total harga transaksi
            'transaction_time' => $transactionTime,  // Waktu transaksi
        ]);

        return response()->json(['success' => "Transaksi berhasil! Total: Rp $totalPrice"], 200);
    }

    public function showHistory()
    {
        // Ambil data riwayat kasir yang sudah tersimpan
        $history = CashierHistory::with('user')->orderBy('transaction_time', 'desc')->get();
    
        // Pendapatan Harian
        $dailyEarnings = CashierHistory::whereDate('transaction_time', today())->sum('total_price');
    
        // Pendapatan Bulanan
        $monthlyEarnings = CashierHistory::whereMonth('transaction_time', now()->month)->sum('total_price');
    
        // Pendapatan Tahunan
        $yearlyEarnings = CashierHistory::whereYear('transaction_time', now()->year)->sum('total_price');
    
        // Kirim data pendapatan dan riwayat kasir ke view
        return view('cashier.history', compact('history', 'dailyEarnings', 'monthlyEarnings', 'yearlyEarnings'));
    }
    
    public function getEarnings(Request $request)
{
    // Pendapatan Harian
    $dailyEarnings = CashierHistory::whereDate('transaction_time', today())->sum('total_price');

    // Pendapatan Bulanan
    $monthlyEarnings = CashierHistory::whereMonth('transaction_time', now()->month)->sum('total_price');

    // Pendapatan Tahunan
    $yearlyEarnings = CashierHistory::whereYear('transaction_time', now()->year)->sum('total_price');

    // Kirim data pendapatan ke view
    return view('cashier.index', compact('dailyEarnings', 'monthlyEarnings', 'yearlyEarnings'));
}

}
