<footer class="bg-gray-900 text-white/80 mt-16">
    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 text-sm">
        <div>
            <div class="text-white text-lg font-bold mb-2">আমার<span class="text-orange-500">শপ</span></div>
            <p>খাঁটি ও প্রাকৃতিক পণ্যের অনলাইন দোকান। মধু, সরিষার তেল, ঘি, খেজুর — সরাসরি আপনার দোরগোড়ায়।</p>
            <div class="flex items-center gap-3 mt-4">
                <a href="{{ config('site.social.facebook') }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="hover:text-orange-500">Facebook</a>
                <a href="{{ config('site.social.youtube') }}" target="_blank" rel="noopener noreferrer" aria-label="YouTube" class="hover:text-orange-500">YouTube</a>
                <a href="{{ config('site.social.instagram') }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="hover:text-orange-500">Instagram</a>
            </div>
        </div>
        <div>
            <div class="text-white font-semibold mb-2">গুরুত্বপূর্ণ লিংক</div>
            <ul class="space-y-1">
                <li><a href="{{ route('about') }}" class="hover:text-orange-500">আমাদের সম্পর্কে</a></li>
                <li><a href="{{ route('shop') }}" class="hover:text-orange-500">সকল পণ্য (Shop)</a></li>
                <li><a href="{{ route('offers') }}" class="hover:text-orange-500">অফার সমূহ</a></li>
                <li><a href="{{ route('blog.index') }}" class="hover:text-orange-500">ব্লগ ও টিপস</a></li>
                <li><a href="{{ route('track') }}" class="hover:text-orange-500">অর্ডার ট্র্যাকিং</a></li>
            </ul>
        </div>
        <div>
            <div class="text-white font-semibold mb-2">পলিসি ও নিয়মাবলী</div>
            <ul class="space-y-1">
                <li><a href="{{ route('delivery') }}" class="hover:text-orange-500">ডেলিভারি পলিসি</a></li>
                <li><a href="{{ route('returns') }}" class="hover:text-orange-500">রিটার্ন ও রিফান্ড পলিসি</a></li>
                <li><a href="{{ route('privacy') }}" class="hover:text-orange-500">প্রাইভেসি পলিসি</a></li>
                <li><a href="{{ route('terms') }}" class="hover:text-orange-500">ব্যবহারের শর্তাবলী</a></li>
                <li><a href="{{ route('contact') }}" class="hover:text-orange-500">যোগাযোগ ও হেল্পলাইন</a></li>
            </ul>
        </div>
        <div>
            <div class="text-white font-semibold mb-2">যোগাযোগ</div>
            <p>ফোন: <a href="tel:{{ config('site.support_phone_raw') }}" class="hover:text-orange-500">{{ config('site.support_phone') }}</a></p>
            <p class="mt-1">ইমেইল: <a href="mailto:{{ config('site.support_email') }}" class="hover:text-orange-500">{{ config('site.support_email') }}</a></p>
            <p class="mt-1 text-xs text-white/60">ঠিকানা: {{ config('site.address') }}</p>
            <div class="text-white font-semibold mt-4 mb-2">পেমেন্ট মেথড</div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="bg-white/10 px-2 py-1 rounded">bKash</span>
                <span class="bg-white/10 px-2 py-1 rounded">Nagad</span>
                <span class="bg-white/10 px-2 py-1 rounded">Rocket</span>
                <span class="bg-white/10 px-2 py-1 rounded">Cash on Delivery</span>
            </div>
        </div>
    </div>
    <div class="border-t border-white/10 py-4 text-center text-xs text-white/50">
        &copy; {{ date('Y') }} {{ config('site.name') }} ({{ config('site.legal_name') }}) — সব অধিকার সংরক্ষিত
    </div>
</footer>
