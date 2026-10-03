@props(['href' => null, 'inverse' => false])

<a href="{{ $href ?? route('home') }}" @class(['group inline-flex items-center gap-2.5 font-semibold tracking-tight', 'text-white' => $inverse, 'text-slate-950' => !$inverse])>
    <span @class(['grid h-9 w-9 grid-cols-2 gap-0.5 rounded-lg p-1.5 shadow-sm', 'bg-white/10' => $inverse, 'bg-indigo-600' => !$inverse]) aria-hidden="true">
        <i class="rounded-[2px] bg-white"></i><i class="rounded-[2px] bg-cyan-300"></i>
        <i class="rounded-[2px] bg-cyan-300"></i><i class="rounded-[2px] bg-white"></i>
    </span>
    <span class="text-[17px]">school<span @class(['font-normal', 'text-indigo-300' => $inverse, 'text-indigo-600' => !$inverse])>qr</span></span>
</a>