<?php
defined( 'ABSPATH' ) or exit;   // show a blank page if try to access this file directly

if ( !class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/* class modified from custom-list-table-example/list-table-example.php */

class Ewz_Item_List extends WP_List_Table
{

    private $default_ipp = 20;
    public $ewz_rows;           // array of data to be displayed
    public $ewz_item_ids;       // arr
    public $is_read_only;       // not currently used, may be needed later
                                //   -- if true, show data but no controls
                                //   -- but data may also need to be filtered

    function __construct( $in_item_ids, $in_headers, $in_rows, $is_read_only=false )
    {
        assert( is_array( $in_item_ids ) );
        assert( is_array( $in_headers ) );
        assert( is_array( $in_rows ) );
        assert( is_bool( $is_read_only ) );

        assert( count( $in_item_ids ) === count( $in_rows ) );
        //Set parent defaults
        parent::__construct( array(
            'singular' => 'item',       //singular name of the listed records
            'plural' => 'items',        //plural name of the listed records
            'ajax' => false             //does this table support ajax?
        ) );
        $this->headers = $in_headers;
        $this->ewz_rows = $in_rows;
        $this->ewz_item_ids = $in_item_ids;
        $this->is_read_only = $is_read_only;
        $this->prepare_items();
    }

    /** ************************************************************************
     * Generally, it's recommended to include a "column_xxx" method for each column 
     * "xxx"you want to render.  But we are passing that value in when we instantiate
     * the table, so don't need that
     *
     * For more detailed insight into how columns are handled, take a look at 
     * WP_List_Table::single_row_columns()
     * 
     * @param array $item A singular item (one full row's worth of data)
     * @param array $column_name The name/slug of the column to be processed
     * @return string Text or HTML to be placed inside the column <td>
     **************************************************************************/
    function column_default( $row, $col_name )
    {   
        // no assert
        // value to display in cell
        // just use the value in the input row, which is generated elsewhere
        if( isset( $row[$col_name] ) ){
            return $row[$col_name];
        } else {
            return '';
        }
    }

    /** ***********************************************************************
     * Here is the one special column we do need - the one containing the checkbox
     **************************************************************************/  
    function column_cb( $row )
    {
        assert( Ewz_Base::is_pos_int( $row[0] ) );
        if( $this->is_read_only ){
            return '';
        } else {
            // override column default, show a checkbox
            return sprintf(
                        '<input type="checkbox" name="%1$s[]" id="ewz_check%2$s_" value="%2$s" />',
                        /* $1%s */ 'ewz_check',
                        /* $2%s */ $row[0]    //The value of the checkbox should be the record's id
                           );
        }
    }

    function get_columns()
    {
        $columns = array();
        $columns[0] = '<input type="checkbox" />';
        foreach ( $this->headers as $n => $header ) {
            if ( $n > 0 ) {
                $columns[$n] = $header;
            }
        }
        return $columns;
    }

   /**
    * Return the array of bulk actions to be shown in the drop-down
    * Also note that list tables are not automatically wrapped in <form> elements,
    * so you will need to create those manually in order for bulk actions to function.
    * 
    * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
    */
    function get_bulk_actions()
    {
        if( $this->is_read_only ){
            $actions = array();
        } else {
            $actions = array(
                             // attach is done via ajax, since list page itself does not change
                             'ewz_attach_imgs'  => 'Attach images to Selected Page',

                             // delete does a submit, because page itself changes
                             'ewz_batch_delete' => 'Delete Selected Items',
                          );
        }
        return $actions;
    }

    // TODO: column sorting
    function prepare_items()
    {
        $user_id = get_current_user_id();
        assert( Ewz_Base::is_pos_int( $user_id ) );
        $per_page = get_user_meta( $user_id, 'ewz_itemsperpage', true );

        if ( empty( $per_page ) || $per_page < 1 ) {
            // set and use the default value if none is set
            update_user_meta( $user_id,'ewz_itemsperpage', $this->default_ipp );
            $per_page = $this->default_ipp;
        }

        // headers
        $columns = $this->get_columns();
        assert( is_array( $columns ) );
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $data = $this->ewz_rows;

        // pagination
        $current_page = $this->get_pagenum();
        assert( Ewz_Base::is_pos_int( $current_page )  );
        $total_items = count( $data );
        $dataslice = array_slice( $data, (($current_page - 1) * $per_page ), $per_page );
        $this->set_pagination_args( array(
            'total_items' => $total_items, 
            'per_page' => $per_page, 
            'total_pages' => ceil( $total_items / $per_page )
        ) );

        $this->items = $dataslice;
    }

}
