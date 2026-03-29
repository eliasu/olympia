@extends('statamic::layout')
@section('title', 'Console Commands')

@section('content')
    <header class="flex items-center justify-between mb-16 mt-12">
        <div>
            <h1 class="text-4xl font-black tracking-tight text-grey-90">Console Commands System</h1>
            <p class="text-grey-60 mt-2 text-lg font-medium">Manage league generation, player statistics, and system cleanup from a single interface.</p>
        </div>
    </header>

    @foreach($commands as $groupHandle => $group)
        <section class="mb-20">
            <div class="flex items-center mb-8">
                <h2 class="text-sm font-black uppercase tracking-[0.3em] text-grey-40 whitespace-nowrap mr-6">{{ $group['title'] }}</h2>
                <div class="h-px bg-grey-20 flex-grow"></div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-12">
                @foreach($group['commands'] as $handle => $cmd)
                    @php $isDestructive = $cmd['destructive'] ?? false; @endphp
                    <div class="overflow-hidden group transition-all duration-300">
                        <form action="{{ cp_route('utilities.console-commands.run') }}" 
                              method="POST" 
                              id="form-{{ Str::slug($handle) }}"
                              @if($isDestructive) onsubmit="return confirm('⚠️ ATTENTION: This action is DESTRUCTIVE and irreversible. Continue?')" @endif>
                            @csrf
                            <input type="hidden" name="command" value="{{ $handle }}">
                            
                            {{-- Action Header --}}
                            <div class="mb-6 flex items-start justify-between">
                                <div class="pr-8">
                                    <div class="flex items-center mb-2">
                                        <h3 class="text-xl font-bold text-grey-80 leading-tight group-hover:text-blue transition-colors">{{ $cmd['title'] }}</h3>
                                        @if($isDestructive)
                                            <span class="ml-4 px-2 py-0.5 text-[10px] uppercase tracking-widest font-black bg-red-100 text-red rounded-full">Danger zone</span>
                                        @endif
                                    </div>
                                    <p class="text-base text-grey-60 leading-relaxed font-medium max-w-xl">{{ $cmd['description'] }}</p>
                                </div>
                                <div class="flex-shrink-0 pt-1">
                                    <button type="submit" 
                                            class="btn {{ $isDestructive ? 'btn-danger' : 'btn-primary' }} shadow-md px-8 py-3 h-auto flex items-center transition-all hover:shadow-lg active:scale-95 text-base font-bold">
                                        <span>Execute</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Config Section --}}
                            @if(isset($cmd['args']) || isset($cmd['options']))
                                <div class="bg-grey-10/40 rounded-2xl p-8 space-y-6">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-6">
                                        
                                        {{-- Arguments --}}
                                        @foreach($cmd['args'] ?? [] as $name => $arg)
                                            <div class="flex flex-col space-y-2">
                                                <label for="arg-{{ $groupHandle }}-{{ $handle }}-{{ $name }}" class="text-[11px] font-black uppercase tracking-widest text-grey-40">{{ $arg['label'] }}</label>
                                                @if($arg['type'] === 'league_slug')
                                                    <div class="relative">
                                                        <select name="{{ $name }}" id="arg-{{ $groupHandle }}-{{ $handle }}-{{ $name }}" class="input-text text-sm pr-10 bg-white border-transparent shadow-sm focus:border-blue/20 rounded-xl py-3 px-4">
                                                            <option value="">-- Select league --</option>
                                                            @foreach($leagues as $league)
                                                                <option value="{{ $league['slug'] }}">{{ $league['title'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        <div class="absolute right-4 top-3.5 pointer-events-none text-grey-40">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                        </div>
                                                    </div>
                                                @else
                                                    <input type="text" name="{{ $name }}" id="arg-{{ $groupHandle }}-{{ $handle }}-{{ $name }}" class="input-text text-sm bg-white border-transparent shadow-sm focus:border-blue/20 rounded-xl py-3 px-4" placeholder="{{ $arg['placeholder'] ?? $name }}">
                                                @endif
                                            </div>
                                        @endforeach

                                        {{-- Options --}}
                                        @foreach($cmd['options'] ?? [] as $name => $opt)
                                            <div class="flex flex-col space-y-2">
                                                <label for="opt-{{ $groupHandle }}-{{ $handle }}-{{ $name }}" class="text-[11px] font-black uppercase tracking-widest text-grey-40">{{ $opt['label'] }}</label>
                                                @if($opt['type'] === 'bool')
                                                    <label class="flex items-center p-3 rounded-xl bg-white border border-transparent shadow-sm cursor-pointer hover:bg-blue/5 transition-all group/checkbox">
                                                        <input type="checkbox" name="{{ $name }}" id="opt-{{ $groupHandle }}-{{ $handle }}-{{ $name }}" class="h-5 w-5 rounded border-grey-20 text-blue focus:ring-blue">
                                                        <span class="ml-4 text-xs font-bold text-grey-60 select-none uppercase tracking-wide">Toggle flag</span>
                                                    </label>
                                                @elseif($opt['type'] === 'number')
                                                    <input type="number" name="{{ $name }}" id="opt-{{ $groupHandle }}-{{ $handle }}-{{ $name }}" class="input-text text-sm bg-white border-transparent shadow-sm focus:border-blue/20 rounded-xl py-3 px-4" value="{{ $opt['default'] ?? '' }}" placeholder="{{ $opt['placeholder'] ?? $name }}">
                                                @else
                                                    <input type="text" name="{{ $name }}" id="opt-{{ $groupHandle }}-{{ $handle }}-{{ $name }}" class="input-text text-sm bg-white border-transparent shadow-sm focus:border-blue/20 rounded-xl py-3 px-4" value="{{ $opt['default'] ?? '' }}" placeholder="{{ $opt['placeholder'] ?? $opt['label'] }}">
                                                @endif
                                            </div>
                                        @endforeach

                                    </div>
                                </div>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
