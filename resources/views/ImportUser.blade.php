<!-- resources/views/import.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Import Users from Excel</title>
</head>
<body>
    <h2>Upload Excel File</h2>
    @if(session('success'))
        <div style="color: green;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="color: red;">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('users.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <label>Select Excel File:</label><br>
        <input type="file" name="file" accept=".xlsx,.xls" required><br><br>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
