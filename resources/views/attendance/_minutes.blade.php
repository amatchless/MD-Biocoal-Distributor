{{ $minutes > 0 ? intdiv($minutes, 60).'h '.str_pad($minutes % 60, 2, '0', STR_PAD_LEFT).'m' : '—' }}
