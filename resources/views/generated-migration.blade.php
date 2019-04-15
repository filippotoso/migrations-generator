
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

@foreach($indexes as $name => $attributes){!! "            " . $attributes['command'] !!}
@endforeach

@foreach($foreignKeys as $name => $attributes){!! "            " . $attributes['command'] !!}
@endforeach
        });
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