
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class {{ $class }} extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('{{ $table }}', function (Blueprint $table) {
@foreach($columns as $name => $attributes){!! "            " . $attributes['command'] !!}
@endforeach
        });
@if (count($indexes)>0 || count($foreignKeys)>0)

        Schema::table('{{ $table }}', function (Blueprint $table) {
@if (count($indexes) > 0)
@foreach($indexes as $name => $attributes){!! "            " . $attributes['command'] !!}
@endforeach
@endif
@if (count($foreignKeys) > 0)

@foreach($foreignKeys as $name => $attributes){!! "            " . $attributes['command'] !!}
@endforeach
@endif
        });
@endif
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('{{ $table }}');
    }

}