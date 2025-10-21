#Terms and Definitions
For the purposes of this document, the following terms and definitions
apply:

<dl>
    <dt>argument</dt>
    <dd>a value passed to a function, that is intended to map to a
    corresponding parameter.</dd>

    <dt>behavior</dt>
    <dd>external appearance or action.</dd>

    <dt>behavior, implementation-defined</dt>
    <dd>behavior specific to an implementation, where that implementation
    must document that behavior.</dd>

    <dt>behavior, undefined</dt>
    <dd>behavior which is not guaranteed to produce any specific result.
    Usually follows an erroneous program construct or data.</dd>

    <dt>behavior, unspecified</dt>
    <dd>behavior for which this specification provides no requirements.</dd>

    <dt>constraint</dt>
    <dd>restriction, either syntactic or semantic, on how language elements
    can be used.</dd>

    <dt>error, fatal</dt>
    <dd>a condition in which the engine cannot continue executing the script
    and must terminate.</dd>

    <dt>error, fatal, catchable</dt>
    <dd>a fatal error that can be caught by a user-defined handler.</dd>

    <dt>error, non-fatal</dt>
    <dd>an error that is not a fatal error and allows for  the engine to
    continue execution.</dd>

    <dt>lvalue</dt>
    <dd>an expression that designates a location that can store a value.</dd>

    <dt>lvalue, modifiable</dt>
    <dd>an lvalue whose value can be changed.</dd>

    <dt>lvalue, non-modifiable</dt>
    <dd>an lvalue whose value cannot be changed.</dd>

    <dt>notice</dt>
    <dd>an informational message informing user of the code that may not work as intended.</dd>

    <dt>parameter</dt>
    <dd>a variable declared in the parameter list of a function that is
    intended to map to a corresponding argument in a call to that
    function.</dd>

    <dt>PHP Run-Time Engine</dt>
    <dd>the software that executes a PHP program. Referred to as <em>the
    Engine</em> throughout this specification.</dd>

    <dt>value</dt>
    <dd>a primitive unit of data operated by the Engine having a type
    and potentially other content depending on the type.</dd>
</dl>

Other terms are defined throughout this specification, as needed, with
the first usage being typeset *like this*.


